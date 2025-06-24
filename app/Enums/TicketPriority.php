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
            self::Medium => '🟡',
            self::High => '🔥',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'bg-blue-100 text-blue-800',
            self::Medium => 'bg-amber-100 text-amber-700',
            self::High => 'bg-rose-100 text-rose-700',
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
