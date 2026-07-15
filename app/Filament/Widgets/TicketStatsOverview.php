<?php

namespace App\Filament\Widgets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Tickets', Ticket::where('status', TicketStatus::Open)->count())
                ->description('Awaiting a first response')
                ->color('info'),

            Stat::make('Unassigned', Ticket::whereNull('assigned_to')
                    ->where('status', '!=', TicketStatus::Closed)
                    ->count())
                ->description('Not yet assigned to staff')
                ->color('danger'),

            Stat::make('High Priority Open', Ticket::where('priority', TicketPriority::High)
                    ->where('status', '!=', TicketStatus::Closed)
                    ->count())
                ->description('Needs urgent attention')
                ->color('warning'),

            Stat::make('Closed This Week', Ticket::where('status', TicketStatus::Closed)
                    ->where('updated_at', '>=', now()->startOfWeek())
                    ->count())
                ->description('Resolved since Monday')
                ->color('success'),
        ];
    }
}
