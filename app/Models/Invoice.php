<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\InvoiceNumberGenerator;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected static function booted()
    {
        static::creating(function (Invoice $invoice) {
            $invoice->status ??= InvoiceStatus::Draft;

            if (empty($invoice->invoice_number)) {
                $next = InvoiceNumberGenerator::next();
                $invoice->invoice_number = $next['number'];
                $invoice->year = $next['year'];
                $invoice->sequence = $next['sequence'];
            }

            $invoice->issue_date ??= now()->toDateString();

            $profile = BusinessProfile::current();

            $invoice->due_date ??= now()
                ->addDays($profile->default_net_days)
                ->toDateString();

            if ($invoice->company_id && empty($invoice->bill_to_name)) {
                $company = Company::find($invoice->company_id);
                $invoice->bill_to_name = $company?->name;
                $invoice->bill_to_address = $company?->address;
            }

            $invoice->from_business_name ??= $profile->business_name;
            $invoice->from_address ??= $profile->address;
            $invoice->from_email ??= $profile->email;
            $invoice->from_phone ??= $profile->phone;
            $invoice->from_tax_id ??= $profile->tax_id;
            $invoice->from_bank_details ??= $profile->bank_details;
            $invoice->from_logo_path ??= $profile->logo_path;

            $invoice->terms ??= $profile->default_terms_text;
        });
    }

    protected $fillable = [
        'company_id',
        'recurring_charge_id',
        'status',
        'issue_date',
        'due_date',
        'bill_to_name',
        'bill_to_address',
        'from_business_name',
        'from_address',
        'from_email',
        'from_phone',
        'from_tax_id',
        'from_bank_details',
        'from_logo_path',
        'terms',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'overdue_reminder_sent_at' => 'datetime',
        'overdue_admin_notified_at' => 'datetime',
        'overdue_ignored_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function recurringCharge()
    {
        return $this->belongsTo(RecurringCharge::class);
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Unpaid balance still owed: Sent/Overdue count their full total,
     * everything else (Draft, Paid, Void) owes nothing.
     */
    public function getBalanceOwedAttribute(): string
    {
        return in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)
            ? (string) $this->total
            : '0.00';
    }

    /**
     * What the company still owes on its *other* open invoices, as of now.
     * This is a read-only summary for display (e.g. "previous balance" on a
     * new invoice) — it never mutates those invoices. Each invoice remains
     * an independent record with its own due date and status, so aging and
     * payment history stay accurate.
     */
    public function getPreviousBalanceAttribute(): string
    {
        if (! $this->company_id) {
            return '0.00';
        }

        return number_format((float) static::query()
            ->where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->sum('total'), 2, '.', '');
    }

    /**
     * This invoice's own balance plus the company's previous balance —
     * what the customer would need to pay to bring the account to zero.
     */
    public function getTotalBalanceDueAttribute(): string
    {
        return number_format((float) $this->balance_owed + (float) $this->previous_balance, 2, '.', '');
    }

    public function recalculateTotal(): static
    {
        $this->total = $this->lineItems()->sum('amount');

        return $this;
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }
}
