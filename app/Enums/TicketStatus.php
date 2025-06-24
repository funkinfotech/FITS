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
            self::Open => '📥',
            self::InProgress => '⏳',
            self::Closed => '💤',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-800',
            self::InProgress => 'bg-amber-100 text-amber-700',
            self::Closed => 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
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
