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
            self::Open => 'bg-funk-lt-blue text-white !important',
            self::InProgress => 'bg-funk-lt-green text-black !important',
            self::Closed => 'bg-gray-300 text-black !important',
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
