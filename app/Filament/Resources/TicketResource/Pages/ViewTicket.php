<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Enums\TicketStatus;
use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.ticket-resource.pages.view-ticket';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addComment')
                ->label('Add Comment')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Forms\Components\Textarea::make('content')
                        ->label('Comment')
                        ->required()
                        ->rows(5)
                        ->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $this->record->comments()->create([
                        'content' => $data['content'],
                        'user_id' => auth()->id(),
                    ]);

                    if ($this->record->status === TicketStatus::Open) {
                        $this->record->update([
                            'status' => TicketStatus::InProgress,
                        ]);
                    }
                }),

            Actions\EditAction::make(),
        ];
    }
}