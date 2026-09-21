<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'receipt_number',
        'year',
        'sequence',
        'amount',
        'paid_date',
        'method',
        'reference',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'pdf_generated_at' => 'datetime',
        'emailed_at' => 'datetime',
        'admin_notified_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function getIsVoidedAttribute(): bool
    {
        return filled($this->voided_at);
    }
}
