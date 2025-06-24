<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';

    public function emoji(): string
    {
        return match ($this) {
            self::Low => '🧊',
            self::Medium => '💧',
            self::High => '🔥',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'text-black !important',
            self::Medium => 'text-black !important',
            self::High => 'bg-funk-orange text-white !important',
        };
    }

    public function filamentColor(): string
    {
        return match($this) {
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'info',
        };
    }
}
