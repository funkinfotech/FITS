<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use App\Enums\TicketStatus;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Conversation';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Textarea::make('content')
                ->label('Comment')
                ->required()
                ->rows(5)
                ->maxLength(5000),

            Hidden::make('user_id')
                ->default(fn () => auth()->id()),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->heading('Conversation')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->after(function () {
                        $ticket = $this->getOwnerRecord();

                        if ($ticket->status === TicketStatus::Open) {
                            $ticket->update([
                                'status' => TicketStatus::InProgress,
                            ]);
                        }
                    }),
            ])
            ->content(view('filament.resources.ticket-resource.relation-managers.ticket-comments-timeline'))
            ->paginated(false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}