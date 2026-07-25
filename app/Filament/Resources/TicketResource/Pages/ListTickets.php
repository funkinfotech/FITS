<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Enums\TicketStatus;
use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Badge;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(TicketResource::getModel()::count()),

            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Open))
                ->badge(TicketResource::getModel()::where('status', TicketStatus::Open)->count()),

            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::InProgress))
                ->badge(TicketResource::getModel()::where('status', TicketStatus::InProgress)->count()),

            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Closed))
                ->badge(TicketResource::getModel()::where('status', TicketStatus::Closed)->count()),

            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('assigned_to'))
                ->badge(TicketResource::getModel()::whereNull('assigned_to')->count())
                ->badgeColor('danger'),
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
                    'Open' => 'success',
                    'In Progress' => 'warning',
                    'Closed' => 'info',
                ])
                ->label('Status'),
        ];
    }
}
