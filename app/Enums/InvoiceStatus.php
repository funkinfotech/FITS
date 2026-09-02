<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'Draft';
    case Sent = 'Sent';
    case Overdue = 'Overdue';
    case Paid = 'Paid';
    case RolledOver = 'Rolled Over';
    case Void = 'Void';

    public function filamentColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Overdue => 'danger',
            self::Paid => 'success',
            self::RolledOver => 'warning',
            self::Void => 'danger',
        };
    }
}
