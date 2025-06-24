<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'Open';
    case InProgress = 'In Progress';
    case Closed = 'Closed';

    public function emoji(): string
    {
        return match ($this) {
            self::Open => '🆕',
            self::InProgress => '⏳',
            self::Closed => '💤',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Open => 'badge badge-status-open',
            self::InProgress => 'badge badge-status-inprogress',
            self::Closed => 'badge badge-status-closed',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::Open => 'primary',
            self::InProgress => 'warning',
            self::Closed => 'success',
        };
    }
}
