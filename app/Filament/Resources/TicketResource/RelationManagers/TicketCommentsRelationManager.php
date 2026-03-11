<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Textarea::make('body')
                ->label('Comment')
                ->required()
                ->maxLength(1000),

            Forms\Components\Hidden::make('user_id')
            ->default(fn () => auth()->id()),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Author'),
                TextColumn::make('body')->wrap(),
                TextColumn::make('created_at')->since()->label('Posted'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
