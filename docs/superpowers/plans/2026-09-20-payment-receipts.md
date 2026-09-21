# Payment Receipts & Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When an admin marks an invoice paid, capture the payment (amount/date/method/reference), generate a branded PDF receipt, email it to selected client contacts, always notify admin users, and provide a top-level Payments admin page with filtering and CSV export for tax/bookkeeping.

**Architecture:** A new `Payment` model/table (one active payment per invoice — full-payment only, no partial-payment tracking) mirrors the existing `Invoice` model's patterns exactly: a `PaymentCounter`-backed sequential number generator, a DomPDF receipt generator, queued Mailables via a `PaymentMailer` support class, and a Filament `PaymentResource`. The existing "Mark as Paid" table action on `InvoiceResource` becomes a form action that creates the `Payment` and triggers the PDF/email flow. Corrections go through a "Void Payment" action (soft-cancel, never edit/delete) that also reopens the invoice.

**Tech Stack:** Laravel 12, Filament 3 (`filament/filament`, `filament/actions` — its native CSV export, no new package), `barryvdh/laravel-dompdf` (already installed), PHPUnit (`Tests\TestCase` + `RefreshDatabase`), Livewire testing helpers for Filament pages.

**Spec:** `docs/superpowers/specs/2026-09-20-payment-receipts-design.md`

## Global Constraints

- One *active* payment per invoice. No partial-payment tracking, no `InvoiceStatus::PartiallyPaid` — explicitly out of scope (see spec's Non-goals).
- Corrections happen via voiding, never by editing or deleting a `Payment` row. Voided rows are kept forever and excluded from `Payment::active()` / exports.
- Client receipt email recipients are chosen per-payment (a checkbox list, same UX as the existing "Email Invoice" action) — never sent automatically to a fixed list.
- The internal "payment received" email to admin users is **not optional** — it always sends regardless of client-email selection.
- No new Composer packages. Filament's export feature (`filament/actions`) and PDF generation (`barryvdh/laravel-dompdf`) are already dependencies.
- All new code follows the exact structural patterns of the existing Invoice feature (`InvoiceNumberGenerator`, `InvoicePdfGenerator`, `InvoiceMailer`, `InvoiceResource`) — same method shapes, same test style (plain PHPUnit, direct model creation via `Model::create()`, no factories beyond the existing `UserFactory`).

---

### Task 1: Migrations for `payment_counters` and `payments`

**Files:**
- Create: `database/migrations/2026_09_20_000001_create_payment_counters_table.php`
- Create: `database/migrations/2026_09_20_000002_create_payments_table.php`

**Interfaces:**
- Produces: tables `payment_counters` (`id`, `year` unique, `last_sequence`, timestamps) and `payments` (`id`, `invoice_id` FK→invoices restrict-on-delete, `receipt_number` unique, `year`, `sequence`, `amount` decimal(10,2), `paid_date` date, `method` string, `reference` nullable string, `notes` nullable text, `pdf_path`/`pdf_generated_at`/`emailed_at`/`admin_notified_at`/`voided_at` nullable, `void_reason` nullable string, `recorded_by` FK→users null-on-delete, timestamps, unique `[year, sequence]`).

- [ ] **Step 1: Write the `payment_counters` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_counters');
    }
};
```

- [ ] **Step 2: Write the `payments` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->decimal('amount', 10, 2);
            $table->date('paid_date');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('admin_notified_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 3: Run the migrations against the local database**

Run: `php artisan migrate`
Expected: both migrations run with no errors; `php artisan migrate:status` shows both as `Ran`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_20_000001_create_payment_counters_table.php database/migrations/2026_09_20_000002_create_payments_table.php
git commit -m "Add payment_counters and payments tables"
```

---

### Task 2: `PaymentMethod` enum, `PaymentCounter` and `Payment` models

**Files:**
- Create: `app/Enums/PaymentMethod.php`
- Create: `app/Models/PaymentCounter.php`
- Create: `app/Models/Payment.php`
- Test: `tests/Feature/PaymentModelTest.php`

**Interfaces:**
- Consumes: `payments`/`payment_counters` tables from Task 1.
- Produces: `App\Enums\PaymentMethod` (cases `Cash`, `Check`, `Ach`, `CreditCard`, `Other`, each a string value); `App\Models\Payment` with fillable `invoice_id, receipt_number, year, sequence, amount, paid_date, method, reference, notes, recorded_by`, relations `invoice(): BelongsTo` and `recordedBy(): BelongsTo`, `scopeActive(Builder $query): Builder` (excludes voided), accessor `getIsVoidedAttribute(): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    protected function makeInvoice(): Invoice
    {
        $company = Company::create(['name' => 'Acme Corp']);

        return Invoice::create(['company_id' => $company->id]);
    }

    public function test_casts_amount_paid_date_and_method(): void
    {
        $invoice = $this->makeInvoice();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '150.5',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Ach->value,
        ]);

        $this->assertSame('150.50', $payment->amount);
        $this->assertSame('2026-09-20', $payment->paid_date->toDateString());
        $this->assertSame(PaymentMethod::Ach, $payment->method);
    }

    public function test_active_scope_excludes_voided_payments(): void
    {
        $invoice = $this->makeInvoice();

        $active = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Cash->value,
        ]);

        $voided = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0002',
            'year' => 2026,
            'sequence' => 2,
            'amount' => '100.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Cash->value,
            'voided_at' => now(),
        ]);

        $activeIds = Payment::active()->pluck('id')->all();

        $this->assertContains($active->id, $activeIds);
        $this->assertNotContains($voided->id, $activeIds);
        $this->assertFalse($active->is_voided);
        $this->assertTrue($voided->is_voided);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentModelTest`
Expected: FAIL — `Class "App\Models\Payment" not found` (or similar, since none of the classes exist yet).

- [ ] **Step 3: Write the `PaymentMethod` enum**

```php
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
```

- [ ] **Step 4: Write the `PaymentCounter` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCounter extends Model
{
    protected $fillable = [
        'year',
        'last_sequence',
    ];
}
```

- [ ] **Step 5: Write the `Payment` model**

```php
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
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PaymentModelTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Enums/PaymentMethod.php app/Models/PaymentCounter.php app/Models/Payment.php tests/Feature/PaymentModelTest.php
git commit -m "Add Payment model, PaymentCounter model, and PaymentMethod enum"
```

---

### Task 3: `PaymentNumberGenerator`

**Files:**
- Create: `app/Support/PaymentNumberGenerator.php`
- Test: `tests/Feature/PaymentNumberGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Models\PaymentCounter` (Task 2).
- Produces: `App\Support\PaymentNumberGenerator::next(?int $year = null): array` returning `['year' => int, 'sequence' => int, 'number' => string]`, number formatted `RCPT-{year}-{sequence:04d}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\PaymentCounter;
use App\Support\PaymentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_calls_produce_gapless_numbers_for_the_current_year(): void
    {
        Carbon::setTestNow('2026-09-20');

        $numbers = [];

        for ($i = 0; $i < 10; $i++) {
            $numbers[] = PaymentNumberGenerator::next()['number'];
        }

        $expected = array_map(fn ($i) => sprintf('RCPT-2026-%04d', $i), range(1, 10));

        $this->assertSame($expected, $numbers);
        $this->assertSame(10, PaymentCounter::where('year', 2026)->value('last_sequence'));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_for_a_new_year_without_touching_the_prior_year(): void
    {
        Carbon::setTestNow('2026-12-31');
        PaymentNumberGenerator::next();
        PaymentNumberGenerator::next();

        Carbon::setTestNow('2027-01-01');
        $next = PaymentNumberGenerator::next();

        $this->assertSame('RCPT-2027-0001', $next['number']);
        $this->assertSame(2, PaymentCounter::where('year', 2026)->value('last_sequence'));
        $this->assertSame(1, PaymentCounter::where('year', 2027)->value('last_sequence'));

        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentNumberGeneratorTest`
Expected: FAIL — `Class "App\Support\PaymentNumberGenerator" not found`.

- [ ] **Step 3: Write the generator**

```php
<?php

namespace App\Support;

use App\Models\PaymentCounter;
use Illuminate\Support\Facades\DB;

class PaymentNumberGenerator
{
    public static function next(?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            $counter = PaymentCounter::where('year', $year)->lockForUpdate()->first()
                ?? PaymentCounter::create(['year' => $year, 'last_sequence' => 0]);

            $counter = PaymentCounter::where('year', $year)->lockForUpdate()->first();
            $counter->increment('last_sequence');

            return [
                'year' => $year,
                'sequence' => $counter->last_sequence,
                'number' => sprintf('RCPT-%d-%04d', $year, $counter->last_sequence),
            ];
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PaymentNumberGeneratorTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/PaymentNumberGenerator.php tests/Feature/PaymentNumberGeneratorTest.php
git commit -m "Add PaymentNumberGenerator"
```

---

### Task 4: `PaymentReceiptPdfGenerator` and the receipt PDF view

**Files:**
- Create: `app/Support/PaymentReceiptPdfGenerator.php`
- Create: `resources/views/pdfs/payment-receipt.blade.php`
- Test: `tests/Feature/PaymentReceiptPdfGenerationTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment` (Task 2), `$payment->invoice` (`App\Models\Invoice`, already in codebase) and `$invoice->lineItems`.
- Produces: `App\Support\PaymentReceiptPdfGenerator::generate(Payment $payment): string` — writes to `payments/{year}/{receipt_number}.pdf` on the `local` disk, sets `$payment->pdf_path` and `$payment->pdf_generated_at`, returns the stored path.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentReceiptPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_writes_a_pdf_to_the_local_disk_and_stamps_the_payment(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '200.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Check->value,
            'reference' => 'Check #4821',
        ]);

        $path = PaymentReceiptPdfGenerator::generate($payment->fresh());

        Storage::disk('local')->assertExists($path);

        $payment->refresh();
        $this->assertSame($path, $payment->pdf_path);
        $this->assertNotNull($payment->pdf_generated_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentReceiptPdfGenerationTest`
Expected: FAIL — `Class "App\Support\PaymentReceiptPdfGenerator" not found`.

- [ ] **Step 3: Write the PDF blade view**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-name {
            font-size: 16px;
            font-weight: bold;
            color: #052a44;
        }
        .muted {
            color: #6b7280;
        }
        .receipt-title {
            font-size: 26px;
            font-weight: bold;
            color: #052a44;
            text-align: right;
        }
        .paid-stamp {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border: 2px solid #15803d;
            color: #15803d;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .panel {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 12px;
        }
        .panel-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #052a44;
            margin-bottom: 4px;
        }
        .line-items th {
            background-color: #052a44;
            color: #ffffff;
            text-align: left;
            padding: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .line-items td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .line-items .amount-col {
            text-align: right;
            white-space: nowrap;
        }
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #052a44;
            border-bottom: none;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #374151;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td width="60%">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ $invoice->from_business_name }}" height="40" style="margin-bottom: 8px;">
            @endif
            <div class="brand-name">{{ $invoice->from_business_name }}</div>
            @if ($invoice->from_address)
                <div class="muted">{{ $invoice->from_address }}</div>
            @endif
            @if ($invoice->from_email)
                <div class="muted">{{ $invoice->from_email }}</div>
            @endif
            @if ($invoice->from_phone)
                <div class="muted">{{ $invoice->from_phone }}</div>
            @endif
            @if ($invoice->from_tax_id)
                <div class="muted">Tax ID: {{ $invoice->from_tax_id }}</div>
            @endif
        </td>
        <td width="40%">
            <div class="receipt-title">RECEIPT</div>
            <div class="muted" style="text-align: right;">{{ $payment->receipt_number }}</div>
            <div style="text-align: right;"><span class="paid-stamp">Paid in Full</span></div>
        </td>
    </tr>
</table>

<table style="margin-top: 24px;">
    <tr>
        <td width="50%" style="padding-right: 8px;">
            <div class="panel">
                <div class="panel-label">Received From</div>
                <div>{{ $invoice->bill_to_name }}</div>
                @if ($invoice->bill_to_address)
                    <div class="muted">{{ $invoice->bill_to_address }}</div>
                @endif
            </div>
        </td>
        <td width="50%" style="padding-left: 8px;">
            <div class="panel">
                <div class="panel-label">Payment Details</div>
                <table>
                    <tr>
                        <td class="muted">Invoice</td>
                        <td>{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Date Paid</td>
                        <td>{{ $payment->paid_date?->format('M j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Method</td>
                        <td>{{ $payment->method->value }}</td>
                    </tr>
                    @if ($payment->reference)
                        <tr>
                            <td class="muted">Reference</td>
                            <td>{{ $payment->reference }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="line-items" style="margin-top: 24px;">
    <thead>
        <tr>
            <th>Description</th>
            <th style="text-align: right;">Qty</th>
            <th style="text-align: right;">Unit Price</th>
            <th style="text-align: right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lineItems as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="amount-col">{{ number_format($item->quantity, 2) }}</td>
                <td class="amount-col">${{ number_format($item->unit_price, 2) }}</td>
                <td class="amount-col">${{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3" style="text-align: right;">Amount Paid</td>
            <td class="amount-col">${{ number_format($payment->amount, 2) }}</td>
        </tr>
    </tbody>
</table>

@if ($payment->notes)
    <div class="footer">
        <p><strong>Notes:</strong> {{ $payment->notes }}</p>
    </div>
@endif

</body>
</html>
```

- [ ] **Step 4: Write the generator**

```php
<?php

namespace App\Support;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptPdfGenerator
{
    public static function generate(Payment $payment): string
    {
        $payment->loadMissing('invoice.lineItems');

        $pdf = Pdf::loadView('pdfs.payment-receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'logoDataUri' => static::logoDataUri($payment),
        ]);

        $path = sprintf('payments/%d/%s.pdf', $payment->year, $payment->receipt_number);
        Storage::disk('local')->put($path, $pdf->output());

        $payment->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->saveQuietly();

        return $path;
    }

    protected static function logoDataUri(Payment $payment): ?string
    {
        $invoice = $payment->invoice;

        if ($invoice->from_logo_path && Storage::disk('public')->exists($invoice->from_logo_path)) {
            $contents = Storage::disk('public')->get($invoice->from_logo_path);
            $mime = Storage::disk('public')->mimeType($invoice->from_logo_path) ?: 'image/png';

            return "data:{$mime};base64," . base64_encode($contents);
        }

        $fallback = public_path('images/funkit-logo.png');

        if (is_file($fallback)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($fallback));
        }

        return null;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PaymentReceiptPdfGenerationTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/PaymentReceiptPdfGenerator.php resources/views/pdfs/payment-receipt.blade.php tests/Feature/PaymentReceiptPdfGenerationTest.php
git commit -m "Add PaymentReceiptPdfGenerator and receipt PDF template"
```

---

### Task 5: Payment mail classes, `PaymentMailer`, and email templates

**Files:**
- Create: `app/Mail/PaymentReceiptMail.php`
- Create: `app/Mail/PaymentReceivedAdminNotification.php`
- Create: `app/Support/PaymentMailer.php`
- Create: `resources/views/emails/payment-receipt.blade.php`
- Create: `resources/views/emails/payment-receipt-text.blade.php`
- Create: `resources/views/emails/payment-received-admin-notification.blade.php`
- Create: `resources/views/emails/payment-received-admin-notification-text.blade.php`
- Test: `tests/Feature/PaymentReceiptMailTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment` (Task 2), `App\Support\PaymentReceiptPdfGenerator::generate()` (Task 4), `App\Models\User` (existing, `is_admin` column), `App\Models\Contact` (existing, `email` accessor).
- Produces: `App\Support\PaymentMailer::sendReceipt(Payment $payment, iterable $contacts): void` (queues `PaymentReceiptMail` to each contact with an email, sets `emailed_at` if at least one was sent) and `App\Support\PaymentMailer::notifyAdmins(Payment $payment): void` (queues `PaymentReceivedAdminNotification` to every `is_admin` user, sets `admin_notified_at`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptMailTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(): Payment
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 200]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '200.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Ach->value,
        ]);
    }

    public function test_send_receipt_queues_one_email_per_contact_with_an_email_and_attaches_the_pdf(): void
    {
        Storage::fake('local');
        Mail::fake();

        $payment = $this->makePayment();

        $withEmail = Contact::create(['company_id' => $payment->invoice->company_id, 'name' => 'Jane Doe']);
        $withEmail->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $withoutEmail = Contact::create(['company_id' => $payment->invoice->company_id, 'name' => 'No Email']);

        PaymentMailer::sendReceipt($payment, [$withEmail, $withoutEmail]);

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($withEmail) {
            return $mail->hasTo($withEmail->email) && count($mail->attachments()) === 1;
        });
        Mail::assertQueued(PaymentReceiptMail::class, 1);

        $payment->refresh();
        $this->assertNotNull($payment->emailed_at);
    }

    public function test_notify_admins_queues_one_email_per_admin_user(): void
    {
        Storage::fake('local');
        Mail::fake();

        $payment = $this->makePayment();

        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);

        PaymentMailer::notifyAdmins($payment);

        Mail::assertQueued(PaymentReceivedAdminNotification::class, function (PaymentReceivedAdminNotification $mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
        Mail::assertQueued(PaymentReceivedAdminNotification::class, 1);

        $payment->refresh();
        $this->assertNotNull($payment->admin_notified_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentReceiptMailTest`
Expected: FAIL — `Class "App\Support\PaymentMailer" not found`.

- [ ] **Step 3: Write `PaymentReceiptMail`**

```php
<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment Receipt — Invoice {$this->payment->invoice->invoice_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
            text: 'emails.payment-receipt-text',
            with: [
                'payment' => $this->payment,
                'invoice' => $this->payment->invoice,
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->payment->pdf_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->payment->pdf_path)
                ->as("{$this->payment->receipt_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

- [ ] **Step 4: Write `PaymentReceivedAdminNotification`**

```php
<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        $invoice = $this->payment->invoice;

        return new Envelope(
            subject: "Payment received — {$invoice->bill_to_name} — {$invoice->invoice_number} — \${$this->payment->amount}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-received-admin-notification',
            text: 'emails.payment-received-admin-notification-text',
            with: [
                'payment' => $this->payment,
                'invoice' => $this->payment->invoice,
                'paymentUrl' => route('filament.admin.resources.payments.view', $this->payment),
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->payment->pdf_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->payment->pdf_path)
                ->as("{$this->payment->receipt_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

- [ ] **Step 5: Write the client receipt email views**

`resources/views/emails/payment-receipt.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Payment Receipt {{ $payment->receipt_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                <tr>
                    <td style="padding-bottom: 20px;">
                        <a href="{{ url('/') }}" style="text-decoration: none;">
                            <img src="{{ asset('images/funkit-logo.png') }}" alt="{{ $invoice->from_business_name }}" width="28" height="28" style="vertical-align: middle; border-radius: 6px;">
                            <span style="vertical-align: middle; margin-left: 8px; font-size: 15px; font-weight: 600; color: #052a44; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">{{ $invoice->from_business_name }}</span>
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #ffffff; border-radius: 10px; padding: 28px;">
                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #15803d;">Payment Received</p>
                        <h1 style="margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827;">{{ $payment->receipt_number }}</h1>
                        <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">For Invoice {{ $invoice->invoice_number }}</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Date Paid</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->paid_date?->format('M j, Y') }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Method</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->method->value }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">Amount Paid</td>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; text-align: right; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">${{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        </table>

                        <p style="margin: 0; font-size: 13px; color: #6b7280;">Thank you! Your receipt is attached to this email as a PDF for your records.</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top: 20px;">
                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                            Questions about this payment? Reply to this email.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
```

`resources/views/emails/payment-receipt-text.blade.php`:

```blade
Payment Receipt {{ $payment->receipt_number }}
{{ $invoice->from_business_name }}

For Invoice: {{ $invoice->invoice_number }}
Date Paid: {{ $payment->paid_date?->format('M j, Y') }}
Method: {{ $payment->method->value }}
Amount Paid: ${{ number_format($payment->amount, 2) }}

Thank you! Your receipt is attached to this email as a PDF for your records.

Questions about this payment? Reply to this email.
```

- [ ] **Step 6: Write the admin notification email views**

`resources/views/emails/payment-received-admin-notification.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Payment received — {{ $payment->receipt_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                <tr>
                    <td style="padding-bottom: 20px;">
                        <span style="font-size: 15px; font-weight: 600; color: #052a44;">{{ $invoice->from_business_name }} — Admin Alert</span>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #ffffff; border-radius: 10px; padding: 28px;">
                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #15803d;">Payment Received</p>
                        <h1 style="margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827;">{{ $invoice->invoice_number }}</h1>
                        <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">Receipt {{ $payment->receipt_number }}</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Company</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $invoice->bill_to_name }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Method</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->method->value }}</td>
                            </tr>
                            @if ($payment->reference)
                                <tr>
                                    <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Reference</td>
                                    <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->reference }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">Amount Paid</td>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; text-align: right; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">${{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        </table>

                        <a href="{{ $paymentUrl }}" style="display: inline-block; background-color: #052a44; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 10px 20px; border-radius: 8px;">View Payment</a>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
```

`resources/views/emails/payment-received-admin-notification-text.blade.php`:

```blade
Payment Received
{{ $invoice->from_business_name }} — Admin Alert

Invoice: {{ $invoice->invoice_number }}
Receipt: {{ $payment->receipt_number }}
Company: {{ $invoice->bill_to_name }}
Method: {{ $payment->method->value }}
@if ($payment->reference)
Reference: {{ $payment->reference }}
@endif
Amount Paid: ${{ number_format($payment->amount, 2) }}

View payment: {{ $paymentUrl }}
```

- [ ] **Step 7: Write `PaymentMailer`**

```php
<?php

namespace App\Support;

use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PaymentMailer
{
    public static function sendReceipt(Payment $payment, iterable $contacts): void
    {
        if (! $payment->pdf_path) {
            PaymentReceiptPdfGenerator::generate($payment);
        }

        $sent = false;

        foreach ($contacts as $contact) {
            if (! $contact->email) {
                continue;
            }

            Mail::to($contact->email)->queue(new PaymentReceiptMail($payment));
            $sent = true;
        }

        if ($sent) {
            $payment->forceFill(['emailed_at' => now()])->saveQuietly();
        }
    }

    public static function notifyAdmins(Payment $payment): void
    {
        if (! $payment->pdf_path) {
            PaymentReceiptPdfGenerator::generate($payment);
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new PaymentReceivedAdminNotification($payment));
        }

        if ($admins->isNotEmpty()) {
            $payment->forceFill(['admin_notified_at' => now()])->saveQuietly();
        }
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=PaymentReceiptMailTest`
Expected: PASS (2 tests). Note: `route('filament.admin.resources.payments.view', ...)` used inside `PaymentReceivedAdminNotification::content()` does not exist yet (created in Task 8) — this is fine for this task's test since `Mail::fake()` means the mailable is never actually rendered/built, only queued and inspected via reflection. If you see a `RouteNotFoundException` here, it means Task 8 hasn't landed yet — confirm you're running tasks in order.

- [ ] **Step 9: Commit**

```bash
git add app/Mail/PaymentReceiptMail.php app/Mail/PaymentReceivedAdminNotification.php app/Support/PaymentMailer.php resources/views/emails/payment-receipt.blade.php resources/views/emails/payment-receipt-text.blade.php resources/views/emails/payment-received-admin-notification.blade.php resources/views/emails/payment-received-admin-notification-text.blade.php tests/Feature/PaymentReceiptMailTest.php
git commit -m "Add payment receipt and admin notification mail"
```

---

### Task 6: Rewrite the "Mark as Paid" action on `InvoiceResource`

**Files:**
- Modify: `app/Filament/Resources/InvoiceResource.php:297-302` (the `mark-as-paid` action) and its `use` imports at the top of the file
- Test: `tests/Feature/MarkInvoiceAsPaidTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment`, `App\Support\PaymentNumberGenerator::next()`, `App\Support\PaymentReceiptPdfGenerator::generate()`, `App\Support\PaymentMailer::sendReceipt()` / `::notifyAdmins()` (all from Tasks 2–5); existing `App\Models\Contact` (already imported in this file).
- Produces: on submit, a `Payment` row, the invoice flipped to `InvoiceStatus::Paid`, a generated PDF, an optional client email, and an always-sent admin email.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarkInvoiceAsPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_invoice_as_paid_records_a_payment_and_notifies(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Ach->value,
                'reference' => 'Transfer #123',
                'notes' => 'Paid in full',
                'contact_ids' => [$contact->id],
            ]);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame(PaymentMethod::Ach, $payment->method);
        $this->assertSame('Transfer #123', $payment->reference);
        $this->assertNotNull($payment->pdf_path);
        $this->assertNotNull($payment->emailed_at);
        $this->assertNotNull($payment->admin_notified_at);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('jane@acme.test'));
        Mail::assertQueued(PaymentReceivedAdminNotification::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_leaving_contacts_unchecked_skips_the_client_email_but_still_notifies_admins(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Cash->value,
                'contact_ids' => [],
            ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($payment->emailed_at);
        $this->assertNotNull($payment->admin_notified_at);

        Mail::assertNotQueued(PaymentReceiptMail::class);
        Mail::assertQueued(PaymentReceivedAdminNotification::class, 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarkInvoiceAsPaidTest`
Expected: FAIL — the current action has no `contact_ids`/`amount`/etc. form fields, so `Payment::where('invoice_id', $invoice->id)->firstOrFail()` throws a `ModelNotFoundException` (no `Payment` row is created by the old bare-confirmation action).

- [ ] **Step 3: Add the new imports to `InvoiceResource.php`**

Add these four lines to the existing `use` block at the top of `app/Filament/Resources/InvoiceResource.php` (alongside the existing `use App\Enums\InvoiceStatus;` etc.):

```php
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\PaymentMailer;
use App\Support\PaymentNumberGenerator;
use App\Support\PaymentReceiptPdfGenerator;
```

`App\Models\Contact`, `Filament\Forms\Components\CheckboxList`, `Filament\Forms\Components\DatePicker`, `Filament\Forms\Components\Select`, `Filament\Forms\Components\Textarea`, `Filament\Forms\Components\TextInput`, and `Filament\Notifications\Notification` are already imported in this file — no changes needed for those.

- [ ] **Step 4: Replace the `mark-as-paid` action**

Replace this block (currently `app/Filament/Resources/InvoiceResource.php:297-302`):

```php
                Action::make('mark-as-paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true))
                    ->action(fn (Invoice $record) => $record->update(['status' => InvoiceStatus::Paid])),
```

with:

```php
                Action::make('mark-as-paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true))
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(fn (Invoice $record) => $record->total),

                        DatePicker::make('paid_date')
                            ->label('Date Paid')
                            ->required()
                            ->default(now()),

                        Select::make('method')
                            ->label('Payment Method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value]))
                            ->required(),

                        TextInput::make('reference')
                            ->label('Reference')
                            ->placeholder('Check #, transaction ID, etc.'),

                        Textarea::make('notes')
                            ->rows(2),

                        CheckboxList::make('contact_ids')
                            ->label('Email receipt to')
                            ->options(fn (Invoice $record): array => $record->company?->contacts
                                ->mapWithKeys(fn ($contact) => [
                                    $contact->id => $contact->name . ($contact->email ? " ({$contact->email})" : ' — no email on file'),
                                ])
                                ->all() ?? [])
                            ->default(fn (Invoice $record): array => $record->company?->contacts
                                ->filter(fn ($contact) => filled($contact->email))
                                ->pluck('id')
                                ->all() ?? []),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $numbering = PaymentNumberGenerator::next();

                        $payment = Payment::create([
                            'invoice_id' => $record->id,
                            'receipt_number' => $numbering['number'],
                            'year' => $numbering['year'],
                            'sequence' => $numbering['sequence'],
                            'amount' => $data['amount'],
                            'paid_date' => $data['paid_date'],
                            'method' => $data['method'],
                            'reference' => $data['reference'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'recorded_by' => auth()->id(),
                        ]);

                        $record->update(['status' => InvoiceStatus::Paid]);

                        PaymentReceiptPdfGenerator::generate($payment);

                        if (filled($data['contact_ids'] ?? [])) {
                            $contacts = Contact::with('emails')->whereIn('id', $data['contact_ids'])->get();
                            PaymentMailer::sendReceipt($payment, $contacts);
                        }

                        PaymentMailer::notifyAdmins($payment);

                        Notification::make()
                            ->title('Payment recorded')
                            ->success()
                            ->send();
                    }),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MarkInvoiceAsPaidTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full Invoice test suite to check for regressions**

Run: `php artisan test --filter=Invoice`
Expected: all existing Invoice-related tests (`InvoiceModelTest`, `InvoiceMailTest`, `InvoicePdfGenerationTest`, `InvoiceResourceTest`, `InvoiceNumberGeneratorTest`, `InvoiceOverdueActionsTest`) still PASS — this action rewrite must not touch any other action or the `form()`/`table()` columns.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/InvoiceResource.php tests/Feature/MarkInvoiceAsPaidTest.php
git commit -m "Turn Mark as Paid into a form that records a Payment and sends receipts"
```

---

### Task 7: `PaymentDownloadController` and its route

**Files:**
- Create: `app/Http/Controllers/PaymentDownloadController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PaymentDownloadControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment` (Task 2), existing `App\Http\Middleware\EnsureUserIsAdmin`.
- Produces: route `payments.download` (`GET /admin/payments/{payment}/download`, `['auth', EnsureUserIsAdmin::class]`), used by `PaymentResource`'s download action in Task 8 and by the admin notification link's sibling in Task 5.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(): Payment
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Cash->value,
        ]);
    }

    public function test_admin_can_download_a_generated_receipt(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->makePayment();
        $payment->update(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf']);
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($admin)
            ->get(route('payments.download', $payment))
            ->assertOk();
    }

    public function test_non_admin_is_redirected_to_access_denied(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $payment = $this->makePayment();

        $this->actingAs($user)
            ->get(route('payments.download', $payment))
            ->assertRedirect(route('access-denied'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentDownloadControllerTest`
Expected: FAIL — `route('payments.download', ...)` doesn't exist yet (`RouteNotFoundException`).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

class PaymentDownloadController extends Controller
{
    public function show(Payment $payment)
    {
        abort_unless(
            $payment->pdf_path && Storage::disk('local')->exists($payment->pdf_path),
            404
        );

        return Storage::disk('local')->download($payment->pdf_path, "{$payment->receipt_number}.pdf");
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import alongside the existing `use App\Http\Controllers\InvoiceDownloadController;`:

```php
use App\Http\Controllers\PaymentDownloadController;
```

And add the route directly below the existing `invoices.download` route:

```php
Route::get('/admin/payments/{payment}/download', [PaymentDownloadController::class, 'show'])
    ->middleware(['auth', EnsureUserIsAdmin::class])
    ->name('payments.download');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PaymentDownloadControllerTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PaymentDownloadController.php routes/web.php tests/Feature/PaymentDownloadControllerTest.php
git commit -m "Add PaymentDownloadController and payments.download route"
```

---

### Task 8: `PaymentResource` — List and View pages, table, filters, infolist

**Files:**
- Create: `app/Filament/Resources/PaymentResource.php`
- Create: `app/Filament/Resources/PaymentResource/Pages/ListPayments.php`
- Create: `app/Filament/Resources/PaymentResource/Pages/ViewPayment.php`
- Test: `tests/Feature/PaymentResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment` (Task 2), `App\Models\Company` (existing), `payments.download` route (Task 7).
- Produces: Filament auto-discovers `PaymentResource` (same mechanism as `InvoiceResource`/`TicketResource` — no explicit registration). Table columns: `receipt_number`, `invoice.invoice_number`, `invoice.company.name`, `amount`, `paid_date`, `method`, `reference`, `is_voided`. Filters: `method`, a company filter, a `paid_date` range filter, and a `hide_voided` toggle (defaulting on) that excludes voided payments from both the list and — since the header export in Task 10 respects active filters — the CSV export by default. Row actions: `ViewAction`, a `download` action visible when `pdf_path` is set. (The `void` action is added in Task 9.)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(string $companyName, string $receiptNumber, int $sequence, PaymentMethod $method): Payment
    {
        $company = Company::create(['name' => $companyName]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => $receiptNumber,
            'year' => 2026,
            'sequence' => $sequence,
            'amount' => '150.00',
            'paid_date' => '2026-09-20',
            'method' => $method->value,
        ]);
    }

    public function test_list_page_shows_payments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $payment = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$payment]);
    }

    public function test_method_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $check = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);
        $cash = $this->makePayment('Beta LLC', 'RCPT-2026-0002', 2, PaymentMethod::Cash);

        Livewire::test(ListPayments::class)
            ->filterTable('method', PaymentMethod::Cash->value)
            ->assertCanSeeTableRecords([$cash])
            ->assertCanNotSeeTableRecords([$check]);
    }

    public function test_voided_payments_are_hidden_by_default_but_visible_when_the_toggle_is_off(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $active = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);
        $voided = $this->makePayment('Beta LLC', 'RCPT-2026-0002', 2, PaymentMethod::Cash);
        $voided->update(['voided_at' => now()]);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$voided])
            ->filterTable('hide_voided', false)
            ->assertCanSeeTableRecords([$active, $voided]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentResourceTest`
Expected: FAIL — `Class "App\Filament\Resources\PaymentResource\Pages\ListPayments" not found`.

- [ ] **Step 3: Write `PaymentResource`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Company;
use App\Models\Payment;
use App\Enums\PaymentMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Billing';

    public static function getNavigationLabel(): string
    {
        return 'Payments';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('receipt_number')->label('Receipt #'),
            TextEntry::make('invoice.invoice_number')->label('Invoice #'),
            TextEntry::make('invoice.company.name')->label('Company')->placeholder('—'),
            TextEntry::make('amount')->money('usd'),
            TextEntry::make('paid_date')->date(),
            TextEntry::make('method')->formatStateUsing(fn ($state) => $state?->value),
            TextEntry::make('reference')->placeholder('—'),
            TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            TextEntry::make('voided_at')
                ->label('Voided At')
                ->dateTime()
                ->visible(fn (Payment $record): bool => $record->is_voided),
            TextEntry::make('void_reason')
                ->label('Void Reason')
                ->placeholder('—')
                ->visible(fn (Payment $record): bool => $record->is_voided),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                TextColumn::make('amount')
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('paid_date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('method')
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),

                TextColumn::make('reference')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_voided')
                    ->label('Voided')
                    ->boolean(),
            ])
            ->defaultSort('paid_date', 'desc')
            ->filters([
                SelectFilter::make('method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])),

                Filter::make('company')
                    ->form([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn () => Company::pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['company_id'] ?? null,
                            fn (Builder $query, $companyId) => $query->whereHas(
                                'invoice',
                                fn (Builder $query) => $query->where('company_id', $companyId)
                            )
                        );
                    }),

                Filter::make('paid_date')
                    ->form([
                        DatePicker::make('paid_from')->label('Paid From'),
                        DatePicker::make('paid_until')->label('Paid Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['paid_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_date', '>=', $date))
                            ->when($data['paid_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_date', '<=', $date));
                    }),

                Filter::make('hide_voided')
                    ->label('Hide voided')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query) => $query->whereNull('voided_at')),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Payment $record): string => route('payments.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => filled($record->pdf_path)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 4: Write the List page**

```php
<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
```

- [ ] **Step 5: Write the View page**

```php
<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PaymentResourceTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Re-run Task 5's mail test now that the route it references exists**

Run: `php artisan test --filter=PaymentReceiptMailTest`
Expected: PASS — confirms `route('filament.admin.resources.payments.view', ...)` in `PaymentReceivedAdminNotification` now resolves.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/PaymentResource.php app/Filament/Resources/PaymentResource/Pages/ListPayments.php app/Filament/Resources/PaymentResource/Pages/ViewPayment.php tests/Feature/PaymentResourceTest.php
git commit -m "Add PaymentResource: list, view, filters, download action"
```

---

### Task 9: "Void Payment" action

**Files:**
- Modify: `app/Filament/Resources/PaymentResource.php` (add imports and the `void` row action)
- Test: `tests/Feature/VoidPaymentTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment::scopeActive()` / `getIsVoidedAttribute()` (Task 2), `App\Enums\InvoiceStatus` (existing).
- Produces: a `void` table action, visible only when `! $record->is_voided`, that sets `voided_at`/`void_reason` on the `Payment` and reopens the related `Invoice` to `Sent` or `Overdue` depending on whether its due date has passed.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class VoidPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function makePaidInvoice(string $dueDate): Invoice
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id, 'due_date' => $dueDate]);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        return $invoice;
    }

    public function test_voiding_a_payment_keeps_the_record_and_reopens_a_not_yet_due_invoice_as_sent(): void
    {
        Carbon::setTestNow('2026-09-20');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $invoice = $this->makePaidInvoice('2026-10-01');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '150.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Check->value,
        ]);

        Livewire::test(ListPayments::class)
            ->callTableAction('void', $payment, data: ['void_reason' => 'Wrong invoice selected']);

        $payment->refresh();
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('Wrong invoice selected', $payment->void_reason);
        $this->assertTrue($payment->is_voided);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);

        Carbon::setTestNow();
    }

    public function test_voiding_a_payment_on_a_past_due_invoice_reopens_it_as_overdue(): void
    {
        Carbon::setTestNow('2026-09-20');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $invoice = $this->makePaidInvoice('2026-09-01');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '150.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Check->value,
        ]);

        Livewire::test(ListPayments::class)
            ->callTableAction('void', $payment);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);

        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=VoidPaymentTest`
Expected: FAIL — `Action "void" does not exist` (no such table action registered yet).

- [ ] **Step 3: Add imports to `PaymentResource.php`**

Add to the existing `use` block:

```php
use App\Enums\InvoiceStatus;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
```

- [ ] **Step 4: Add the `void` action**

In `table()`, append this action inside the existing `->actions([...])` array, after the `download` action:

```php
                Action::make('void')
                    ->label('Void Payment')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => ! $record->is_voided)
                    ->form([
                        TextInput::make('void_reason')
                            ->label('Reason (optional)'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $record->update([
                            'voided_at' => now(),
                            'void_reason' => $data['void_reason'] ?? null,
                        ]);

                        $invoice = $record->invoice;
                        $invoice->update([
                            'status' => $invoice->due_date->isPast()
                                ? InvoiceStatus::Overdue
                                : InvoiceStatus::Sent,
                        ]);

                        Notification::make()
                            ->title('Payment voided')
                            ->success()
                            ->send();
                    }),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=VoidPaymentTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/PaymentResource.php tests/Feature/VoidPaymentTest.php
git commit -m "Add Void Payment action that reopens the invoice"
```

---

### Task 10: `PaymentExporter` and CSV export wiring

**Files:**
- Create: `app/Filament/Exports/PaymentExporter.php`
- Modify: `app/Filament/Resources/PaymentResource.php` (add `headerActions`/change `bulkActions`)
- Test: `tests/Feature/PaymentExporterTest.php`

**Interfaces:**
- Consumes: `App\Models\Payment` (Task 2), Filament's `Filament\Actions\Exports\Exporter` / `ExportColumn` (already in `filament/actions`, a transitive dependency of `filament/filament` — no new Composer package).
- Produces: `App\Filament\Exports\PaymentExporter` wired into `PaymentResource`'s table as a header `ExportAction` (respects active filters, no row selection needed) and a `ExportBulkAction`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Exports\PaymentExporter;
use Tests\TestCase;

class PaymentExporterTest extends TestCase
{
    public function test_columns_match_the_expected_ledger_shape(): void
    {
        $columns = collect(PaymentExporter::getColumns())->map(fn ($column) => $column->getName())->all();

        $this->assertSame([
            'paid_date',
            'receipt_number',
            'invoice.invoice_number',
            'invoice.company.name',
            'amount',
            'method',
            'reference',
            'notes',
        ], $columns);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentExporterTest`
Expected: FAIL — `Class "App\Filament\Exports\PaymentExporter" not found`.

- [ ] **Step 3: Write the exporter**

```php
<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PaymentExporter extends Exporter
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('paid_date')
                ->label('Date'),

            ExportColumn::make('receipt_number')
                ->label('Receipt #'),

            ExportColumn::make('invoice.invoice_number')
                ->label('Invoice #'),

            ExportColumn::make('invoice.company.name')
                ->label('Company'),

            ExportColumn::make('amount')
                ->label('Amount'),

            ExportColumn::make('method')
                ->label('Method')
                ->formatStateUsing(fn ($state) => $state?->value),

            ExportColumn::make('reference')
                ->label('Reference'),

            ExportColumn::make('notes')
                ->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
```

- [ ] **Step 4: Wire the exporter into `PaymentResource`'s table**

Add to the `use` block in `app/Filament/Resources/PaymentResource.php`:

```php
use App\Filament\Exports\PaymentExporter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
```

In `table()`, add a `->headerActions([...])` call and a `->bulkActions([...])` call (place both after `->filters([...])` and before `->actions([...])`):

```php
            ->headerActions([
                ExportAction::make()
                    ->exporter(PaymentExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PaymentExporter::class),
                ]),
            ])
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PaymentExporterTest`
Expected: PASS.

- [ ] **Step 6: Run the full Payment test suite to check for regressions**

Run: `php artisan test --filter=Payment`
Expected: every test written across Tasks 2–10 (`PaymentModelTest`, `PaymentNumberGeneratorTest`, `PaymentReceiptPdfGenerationTest`, `PaymentReceiptMailTest`, `MarkInvoiceAsPaidTest`, `PaymentDownloadControllerTest`, `PaymentResourceTest`, `VoidPaymentTest`, `PaymentExporterTest`) PASSes.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Exports/PaymentExporter.php app/Filament/Resources/PaymentResource.php tests/Feature/PaymentExporterTest.php
git commit -m "Add PaymentExporter and wire CSV export into PaymentResource"
```

---

## Final verification

- [ ] Run the entire suite once more: `php artisan test` — expect zero failures (the pre-existing suite requires MySQL via Sail; if running against sqlite locally, the unrelated `2026_07_25_000006_make_user_id_nullable_on_tickets_table` migration will fail on `ALTER TABLE ... MODIFY` — this is a pre-existing condition unrelated to this plan, not a regression to chase here).
- [ ] Manually click through in the browser: open an invoice with status Sent → Mark as Paid → fill the form → confirm the receipt PDF downloads correctly from the Payments list → confirm the client email (if a contact was checked) and the admin email both arrive (check `storage/logs/laravel.log` or Mailtrap/whatever `MAIL_MAILER` is configured locally) → Void the payment → confirm the invoice's "Mark as Paid" button reappears.
