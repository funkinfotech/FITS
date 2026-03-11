<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

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
            ->columns([
                TextColumn::make('user.name')->label('Author'),
                TextColumn::make('content')->wrap(),
                TextColumn::make('created_at')->since()->label('Posted'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right'),
                    ->after(function () {
                        $ticket = $this->getOwnerRecord();

                        if ($ticket->status === \App\Enums\TicketStatus::Open) {
                            $ticket->update([
                                'status' => \App\Enums\TicketStatus::InProgress,
                            ]);
                        }
                    })
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