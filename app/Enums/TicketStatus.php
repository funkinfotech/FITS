<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'Open';
    case InProgress = 'In Progress';
    case Closed = 'Closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => '📬 Open',
            self::InProgress => '⏳ In Progress',
            self::Closed => '✅ Closed',
        };
    }
}