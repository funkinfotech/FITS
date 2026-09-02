<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringCharge extends Model
{
    protected $fillable = [
        'company_id',
        'description',
        'amount',
        'billing_day',
        'is_active',
        'starts_on',
        'ends_on',
        'last_invoiced_on',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_day' => 'integer',
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'last_invoiced_on' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The next date an invoice is due to be generated, anchored off the last
     * cycle billed (or starts_on if never billed) rather than "today", so a
     * late-running scheduler doesn't shift the billing cadence.
     */
    public function nextDueDate(): \Illuminate\Support\Carbon
    {
        $anchor = $this->last_invoiced_on ?? $this->starts_on->copy()->subMonth();

        return $anchor->copy()->addMonth()->day(min($this->billing_day, $anchor->copy()->addMonth()->daysInMonth));
    }

    public function isDue(\Illuminate\Support\Carbon $asOf): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->ends_on && $asOf->gt($this->ends_on)) {
            return false;
        }

        return ! $asOf->lt($this->nextDueDate());
    }
}
