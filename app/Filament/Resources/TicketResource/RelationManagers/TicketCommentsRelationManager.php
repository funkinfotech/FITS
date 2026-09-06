<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use App\Enums\TicketStatus;
use App\Exceptions\RejectedAttachment;
use App\Models\Comment;
use App\Support\AttachmentPipeline;
use App\Support\TicketMailer;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Tables\Columns\Layout\View as ViewLayout;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected ?string $pendingTicketStatus = null;

    /** @var array<int, \Illuminate\Http\UploadedFile> */
    protected array $pendingAttachments = [];

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Textarea::make('content')
                ->label('Comment')
                ->required()
                ->maxLength(1000),

            Toggle::make('is_internal')
                ->label('Internal note (hidden from customer)')
                ->helperText('Internal notes are never emailed to anyone.')
                ->live()
                ->default(false),

            Select::make('ticket_status')
                ->label('Set Status')
                ->options(collect(TicketStatus::cases())
                    ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                )
                ->native(false)
                ->default(function () {
                    $ticket = $this->getOwnerRecord();

                    return $ticket->status === TicketStatus::Open
                        ? TicketStatus::InProgress->value
                        : $ticket->status->value;
                }),

            CheckboxList::make('recipients')
                ->label('Notify these contacts')
                // Internal notes don't notify anyone; hiding the field also stops
                // Filament from writing the comment_contact pivot rows.
                ->hidden(fn (Forms\Get $get): bool => (bool) $get('is_internal'))
                ->relationship('recipients', 'name', modifyQueryUsing: fn (Builder $query) =>
                    $query->where('company_id', $this->getOwnerRecord()->company_id))
                ->default(fn () => $this->getOwnerRecord()->contact_id
                    ? [$this->getOwnerRecord()->contact_id]
                    : [])
                ->columns(2),

            FileUpload::make('attachments')
                ->label('Attachments')
                ->multiple()
                ->storeFiles(false) // handed to AttachmentPipeline, never stored by Filament
                ->openable()
                ->reorderable(false)
                ->maxFiles(config('attachments.max_files'))
                ->maxSize(config('attachments.max_size_kb'))
                ->acceptedFileTypes(static::acceptedMimeTypes())
                ->helperText('Images, PDF, Office documents, text/logs or .zip — sanitised on upload.'),

            Hidden::make('user_id')
                ->default(fn () => auth()->id()),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function acceptedMimeTypes(): array
    {
        return collect(config('attachments.allowed'))
            ->flatten()
            ->reject(fn (string $mime): bool => $mime === 'application/octet-stream')
            ->unique()
            ->values()
            ->all();
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->heading('Conversation')
            ->modifyQueryUsing(function (Builder $query) {
                // The comments() relationship is ordered oldest-first; the agent
                // wants the newest reply on top here.
                $query->getQuery()->orders = null;

                $query
                    ->orderByDesc('created_at')
                    ->with(['user', 'contact.emails', 'recipients', 'attachments']);
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                ViewLayout::make('filament.resources.ticket-resource.relation-managers.comment-card'),
            ])
            ->contentGrid(['default' => 1])
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->modalSubmitActionLabel('Send')
                    ->createAnother(false)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->pendingTicketStatus = $data['ticket_status'] ?? null;
                        $this->pendingAttachments = array_values(array_filter((array) ($data['attachments'] ?? [])));
                        unset($data['ticket_status'], $data['attachments']);

                        return $data;
                    })
                    ->after(function (Comment $record) {
                        $ticket = $this->getOwnerRecord();

                        if ($this->pendingTicketStatus && $this->pendingTicketStatus !== $ticket->status->value) {
                            $ticket->update(['status' => $this->pendingTicketStatus]);
                            $this->dispatch('ticket-updated');
                        }

                        $this->storePendingAttachments($record);

                        if ($record->is_internal) {
                            static::clearRecipients($record);

                            return;
                        }

                        TicketMailer::sendReply($record);
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->mutateFormDataUsing(function (array $data): array {
                            $this->pendingAttachments = array_values(array_filter((array) ($data['attachments'] ?? [])));
                            unset($data['attachments']);

                            return $data;
                        })
                        ->after(function (Comment $record) {
                            $this->storePendingAttachments($record);

                            if ($record->is_internal) {
                                static::clearRecipients($record);
                            }
                        }),
                    Tables\Actions\DeleteAction::make(),
                ])->visible(fn (Comment $record): bool => $record->exists),
            ], position: ActionsPosition::AfterContent);
    }

    protected function storePendingAttachments(Comment $comment): void
    {
        foreach ($this->pendingAttachments as $file) {
            try {
                AttachmentPipeline::fromUpload($file, $comment, auth()->user());
            } catch (RejectedAttachment $e) {
                Notification::make()
                    ->title('Attachment skipped')
                    ->body(AttachmentPipeline::sanitizeName($file->getClientOriginalName()) . ' — ' . $e->getMessage())
                    ->warning()
                    ->send();
            } catch (\Throwable $e) {
                report($e);
                Notification::make()->title('An attachment could not be processed.')->danger()->send();
            }
        }

        $this->pendingAttachments = [];
    }

    /**
     * An internal note must never carry notification recipients.
     */
    protected static function clearRecipients(Comment $comment): void
    {
        $comment->recipients()->detach();
        $comment->unsetRelation('recipients');
    }

    /**
     * Append the ticket's original message as the last (oldest) entry so the
     * whole thread reads as one list.
     */
    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        $records = parent::getTableRecords();

        if (
            $records instanceof Collection &&
            ! $records->contains(fn (Comment $record): bool => (bool) ($record->is_original_message ?? false))
        ) {
            $records->push($this->makeOriginalMessageRow());
        }

        return $records;
    }

    protected function makeOriginalMessageRow(): Comment
    {
        $ticket = $this->getOwnerRecord();

        $row = new Comment([
            'content' => $ticket->message,
            'is_internal' => false,
        ]);

        $row->id = 'original-message';
        $row->created_at = $ticket->created_at;
        $row->exists = false;
        $row->is_original_message = true;
        $row->author_name = $ticket->name ?: ($ticket->contact?->name ?? 'Unknown sender');
        $row->author_email = $ticket->email;

        $row->setRelation('user', null);
        $row->setRelation('contact', $ticket->contact);
        $row->setRelation('recipients', new Collection());
        $row->setRelation('attachments', $ticket->attachments()->get());

        return $row;
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
