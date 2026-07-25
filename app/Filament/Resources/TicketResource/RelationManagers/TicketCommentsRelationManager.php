<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use App\Enums\TicketStatus;
use App\Support\TicketMailer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Builder;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected ?string $pendingTicketStatus = null;

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Textarea::make('content')
                ->label('Comment')
                ->required()
                ->maxLength(1000),

            Toggle::make('is_internal')
                ->label('Internal note (hidden from customer)')
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
                ->relationship('recipients', 'name', modifyQueryUsing: fn (Builder $query) =>
                    $query->where('company_id', $this->getOwnerRecord()->company_id))
                ->default(fn () => $this->getOwnerRecord()->contact_id
                    ? [$this->getOwnerRecord()->contact_id]
                    : [])
                ->columns(2),

            Hidden::make('user_id')
                ->default(fn () => auth()->id()),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->heading('Conversation')
            ->columns([
                IconColumn::make('is_internal')
                    ->label('')
                    ->icon(fn ($state) => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-chat-bubble-left-right')
                    ->color(fn ($state) => $state ? 'warning' : 'success'),

                TextColumn::make('user.name')
                    ->label('')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record) => $state ?? $record->contact?->name ?? 'Guest'),

                TextColumn::make('content')
                    ->label('')
                    ->wrap(),

                TextColumn::make('recipients.name')
                    ->label('Notified')
                    ->badge()
                    ->listWithLineBreaks()
                    ->default('—'),

                TextColumn::make('created_at')
                    ->since()
                    ->label('')
                    ->color('grey'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->modalSubmitActionLabel('Send')
                    ->createAnother(false)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->mutateFormDataUsing(function (array $data): array {
                        $this->pendingTicketStatus = $data['ticket_status'] ?? null;
                        unset($data['ticket_status']);

                        return $data;
                    })
                    ->after(function ($record) {
                        $ticket = $this->getOwnerRecord();

                        if ($this->pendingTicketStatus && $this->pendingTicketStatus !== $ticket->status->value) {
                            $ticket->update(['status' => $this->pendingTicketStatus]);
                            $this->dispatch('ticket-updated');
                        }

                        if (! $record->is_internal) {
                            TicketMailer::sendReply($record);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
