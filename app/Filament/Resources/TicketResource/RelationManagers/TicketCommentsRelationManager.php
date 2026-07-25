<?php

namespace App\Filament\Resources\TicketResource\RelationManagers;

use App\Enums\TicketStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

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

            Toggle::make('is_internal')
                ->label('Internal note (hidden from customer)')
                ->default(false),

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
                    ->default('Guest'),

                TextColumn::make('content')
                    ->label('')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->since()
                    ->label('')
                    ->color('grey'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->after(function ($record) {
                        $ticket = $this->getOwnerRecord();

                        if (! $record->is_internal && $ticket->status === TicketStatus::Open) {
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
