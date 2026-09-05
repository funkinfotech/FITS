<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function recurringCharges()
    {
        return $this->hasMany(RecurringCharge::class);
    }

    /**
     * Sum of Sent/Overdue invoice totals — what this company currently owes.
     */
    public function getBalanceOwedAttribute(): string
    {
        return number_format((float) $this->invoices()
            ->whereIn('status', [\App\Enums\InvoiceStatus::Sent, \App\Enums\InvoiceStatus::Overdue])
            ->sum('total'), 2, '.', '');
    }
}
