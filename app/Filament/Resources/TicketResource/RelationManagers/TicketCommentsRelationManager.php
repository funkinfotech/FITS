<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use App\Enums\TicketStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Textarea::make('content')
                ->label('Comment')
                ->required()
                ->maxLength(1000),

            Hidden::make('user_id')
                ->default(fn () => auth()->id()),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->heading('Conversation')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->default('Guest'),

                TextColumn::make('content')
                    ->label('Comment')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->since()
                    ->label('Posted'),
            ])
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