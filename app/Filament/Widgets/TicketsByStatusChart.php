<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Tickets by Status';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $counts = collect(TicketStatus::cases())
            ->mapWithKeys(fn ($status) => [
                $status->value => Ticket::where('status', $status)->count(),
            ]);

        return [
            'datasets' => [
                [
                    'data' => $counts->values(),
                    'backgroundColor' => ['#6366f1', '#f59e0b', '#10b981'],
                ],
            ],
            'labels' => $counts->keys(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
