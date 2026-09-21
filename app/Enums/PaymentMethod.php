<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'Cash';
    case Check = 'Check';
    case Ach = 'ACH / Bank Transfer';
    case CreditCard = 'Credit/Debit Card';
    case Other = 'Other';
}
