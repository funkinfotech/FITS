<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Badge;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('ticket_number')
                ->label('Ticket #')
                ->disabled(),

            TextInput::make('name')
                ->disabled(),

            TextInput::make('email')
                ->disabled(),

            TextInput::make('subject')
                ->disabled(),

            Textarea::make('message')
                ->disabled()
                ->rows(6),

            Badge::make('priority')
                ->colors([
                    'Low' => 'gray',
                    'Medium' => 'warning',
                    'High' => 'danger',
                ])
                ->label('Priority'),

            Badge::make('status')
                ->colors([
                    'Open' => 'info',
                    'In Progress' => 'warning',
                    'Closed' => 'success',
                ])
                ->label('Status'),
        ];
    }
}
