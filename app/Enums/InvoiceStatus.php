<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'Draft';
    case Sent = 'Sent';
    case Overdue = 'Overdue';
    case Paid = 'Paid';
    case Void = 'Void';

    public function filamentColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Overdue => 'danger',
            self::Paid => 'success',
            self::Void => 'danger',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'badge badge-invoice-draft',
            self::Sent => 'badge badge-invoice-sent',
            self::Overdue => 'badge badge-invoice-overdue',
            self::Paid => 'badge badge-invoice-paid',
            self::Void => 'badge badge-invoice-void',
        };
    }
}
