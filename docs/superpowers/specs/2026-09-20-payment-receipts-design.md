# Payment Receipts & Tracking — Design

Date: 2026-09-20
Status: Approved by user, ready for implementation planning

## Problem

The Filament "Mark as Paid" action on an invoice (`app/Filament/Resources/InvoiceResource.php:297-302`) is currently a single status flip: `$record->update(['status' => InvoiceStatus::Paid])`. It records no amount, date, method, or reference, sends no confirmation to the client or the admin, and produces no artifact usable for bookkeeping or tax filing.

The user (a solo operator running invoicing through this app) needs, at the moment an invoice is marked paid:
1. A branded receipt PDF emailed to the client.
2. An internal notification email to admin users confirming the payment.
3. A permanent internal record suitable for exporting end-of-year financial/tax data.

## Goals

- Capture amount, date, method, and reference when an invoice is marked paid.
- Generate a branded PDF receipt, styled consistently with the existing invoice PDF.
- Email the receipt to client contacts (selectable per payment, not forced).
- Always email admin users an internal payment-received notice.
- Provide a top-level "Payments" admin list with filtering and CSV export for tax/bookkeeping handoff.
- Support correcting mistakes via voiding (audit trail preserved), not edit/delete.

## Non-goals (explicitly out of scope for this iteration)

- **Partial payments.** One invoice = at most one *active* payment record. No running-balance tracking, no `InvoiceStatus::PartiallyPaid`. If this is needed later it's a separate design (touches `Invoice::getBalanceOwedAttribute()` and the overdue cron logic).
- Payment processor integration (Stripe, etc.) — this only records payments that already happened, it doesn't collect them.
- Automated "payment voided" email to the client — voiding is silent to the client; the admin handles that conversation manually if needed.
- Editing a posted payment's amount/date/method — only voiding is supported, per the user's explicit choice.

## Data model

### New table: `payment_counters`

Mirrors `invoice_counters` (`app/Models/InvoiceCounter.php`), one row per year, used to generate sequential receipt numbers.

```php
Schema::create('payment_counters', function (Blueprint $table) {
    $table->id();
    $table->unsignedSmallInteger('year')->unique();
    $table->unsignedInteger('last_sequence')->default(0);
    $table->timestamps();
});
```

### New table: `payments`

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
    $table->string('receipt_number')->unique();
    $table->unsignedSmallInteger('year');
    $table->unsignedInteger('sequence');
    $table->decimal('amount', 10, 2);
    $table->date('paid_date');
    $table->string('method'); // PaymentMethod enum value
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
```

Rationale for `restrictOnDelete()` on `invoice_id`: unlike other FKs in this app (which `nullOnDelete`), an invoice with a payment record must never be deletable out from under a financial record — deleting the invoice should fail loudly.

### `App\Enums\PaymentMethod`

String-backed enum, same shape as `InvoiceStatus`:

```php
enum PaymentMethod: string
{
    case Cash = 'Cash';
    case Check = 'Check';
    case Ach = 'ACH / Bank Transfer';
    case CreditCard = 'Credit/Debit Card';
    case Other = 'Other';
}
```

### `App\Models\Payment`

- `belongsTo(Invoice::class)`
- `belongsTo(User::class, 'recorded_by')`
- Casts: `method` → `PaymentMethod::class`, `paid_date` → `date`, `amount` → `decimal:2`, `pdf_generated_at`/`emailed_at`/`admin_notified_at`/`voided_at` → `datetime`
- `scopeActive($query)` → `whereNull('voided_at')`
- `getIsVoidedAttribute(): bool` → `filled($this->voided_at)`

### `App\Support\PaymentNumberGenerator`

Direct mirror of `InvoiceNumberGenerator` (`app/Support/InvoiceNumberGenerator.php`), using `PaymentCounter` and format `RCPT-%d-%04d`.

## Mark-as-paid flow

Replace the existing bare-confirmation action in `InvoiceResource::table()` (`app/Filament/Resources/InvoiceResource.php:297-302`) with a form action.

**Form fields:**
- `amount` — number, required, default `$record->total`
- `paid_date` — date picker, required, default `today()`
- `method` — select from `PaymentMethod::cases()`, required
- `reference` — text, optional
- `notes` — textarea, optional
- `contact_ids` — `CheckboxList`, same options/default pattern as the existing "Email Invoice" action (`app/Filament/Resources/InvoiceResource.php:269-280`): options = the invoice's company's contacts (label includes email or "no email on file"), default = contacts with an email on file. Not required — leaving all unchecked means no client email is sent, everything else still happens.

**Visibility:** unchanged — only shown when `$record->status` is `Sent` or `Overdue`.

**Action (on submit), in order:**
1. `PaymentNumberGenerator::next()` → receipt number/year/sequence.
2. Create the `Payment` row: `invoice_id`, the generated numbering, form data, `recorded_by = auth()->id()`.
3. `$record->update(['status' => InvoiceStatus::Paid])`.
4. `PaymentReceiptPdfGenerator::generate($payment)`.
5. If `contact_ids` non-empty: load those `Contact` models, `PaymentMailer::sendReceipt($payment, $contacts)` — queues `PaymentReceiptMail` per contact with an email, sets `emailed_at`.
6. `PaymentMailer::notifyAdmins($payment)` — always runs, loops `User::where('is_admin', true)`, queues `PaymentReceivedAdminNotification` per admin, sets `admin_notified_at`.
7. `Notification::make()->title('Payment recorded')->success()->send()`.

## Void flow

New row action `Action::make('void')` on the Payments list (see below), visible only when `! $record->is_voided`.

**Form:** `void_reason` — text, optional.

**Action:**
1. `$payment->update(['voided_at' => now(), 'void_reason' => $data['void_reason']])`.
2. Reopen the invoice: if `$payment->invoice->due_date->isPast()` → `InvoiceStatus::Overdue`, else `InvoiceStatus::Sent`. (The nightly `invoices:mark-overdue` cron would eventually catch this too, but setting it immediately avoids a stale "Sent" showing for up to a day.)
3. No email sent.
4. Success notification.

The existing "Mark as Paid" action's visibility condition (`status in [Sent, Overdue]`) means voiding correctly makes it reappear on the invoice.

## Filament: `PaymentResource`

New top-level resource, `app/Filament/Resources/PaymentResource.php`, registered via Filament's auto-discovery (same mechanism as `InvoiceResource`/`TicketResource` — no explicit registration needed).

**Table columns:** `receipt_number`, `invoice.invoice_number` (linked), `invoice.company.name`, `amount` (currency), `paid_date` (sortable, default sort desc), `method` (badge), `reference`, a "Voided" badge column visible only when voided.

**Filters:** date range on `paid_date`, `method` (select), `company` (via `invoice.company_id` relationship filter), an active/voided toggle defaulting to active-only.

**Row actions:** `ViewAction`, a "Download PDF" action (same pattern as `InvoiceResource`'s download action, visible when `pdf_path` is set), the `void` action described above.

**Header actions / bulk actions:** Filament's built-in `Tables\Actions\ExportAction` (header, respects active filters — no need to select rows) and `Tables\Actions\ExportBulkAction`, both backed by a new `App\Filament\Exports\PaymentExporter` (`Filament\Actions\Exports\Exporter`) with columns: Date, Receipt #, Invoice #, Company, Amount, Method, Reference, Notes. This uses `filament/actions`' native export (already a transitive dependency — confirmed via the existing `filament/exports/{export}/download` route — no new package needed).

**Pages:** `List` and `View` only. No `Create`/`Edit` — payments only originate from the mark-as-paid action; corrections go through void.

## PDF & email

### `App\Support\PaymentReceiptPdfGenerator`

Mirrors `InvoicePdfGenerator` (`app/Support/InvoicePdfGenerator.php`): loads `$payment->invoice->lineItems`, renders a new `resources/views/pdfs/payment-receipt.blade.php` view, stores to `payments/{year}/{receipt_number}.pdf` on the `local` disk, sets `pdf_path`/`pdf_generated_at`.

`pdfs/payment-receipt.blade.php` is a variant of `pdfs/invoice.blade.php`: same business header/logo treatment (reuses the invoice's `from_*` snapshot fields), "RECEIPT" title instead of "INVOICE", the invoice's line items shown for context ("payment applied to Invoice INV-2026-0012"), then a summary block: Amount Paid, Date Paid, Method, Reference, and a "PAID IN FULL" badge/stamp treatment.

### `App\Mail\PaymentReceiptMail` (client-facing)

Mirrors `InvoiceMail`: subject `"Payment Receipt — Invoice {invoice_number}"`, views `emails.payment-receipt` / `emails.payment-receipt-text`, attaches the PDF from `payment->pdf_path`.

### `App\Mail\PaymentReceivedAdminNotification` (internal)

Mirrors `InvoiceOverdueAdminNotification`: subject `"Payment received — {company} — {invoice_number} — ${amount}"`, views `emails.payment-received-admin-notification` / `-text`, includes company/invoice/amount/method/date and a link to the payment in the admin panel; also attaches the PDF (useful for forwarding straight to a bookkeeper).

### `App\Support\PaymentMailer`

Mirrors `InvoiceMailer`:
- `sendReceipt(Payment $payment, iterable $contacts): void` — queues `PaymentReceiptMail` to each contact with an email, sets `emailed_at`.
- `notifyAdmins(Payment $payment): void` — queues `PaymentReceivedAdminNotification` to each admin user, sets `admin_notified_at`.

## Routes

`App\Http\Controllers\PaymentDownloadController@show`, mirroring `InvoiceDownloadController`: `GET /admin/payments/{payment}/download`, name `payments.download`, middleware `['auth', EnsureUserIsAdmin::class]` (same guard as `invoices.download` in `routes/web.php`).

## Testing plan

Mirrors the existing Invoice test suite, same style (plain PHPUnit `TestCase` + `RefreshDatabase`, direct model creation, `Livewire::test()` for Filament pages):

- `PaymentNumberGeneratorTest` — sequential numbering per year, mirrors `InvoiceNumberGeneratorTest`.
- `PaymentModelTest` — casts, `active()` scope excludes voided, `is_voided` accessor.
- `PaymentReceiptPdfGenerationTest` — generates a PDF, sets `pdf_path`/`pdf_generated_at`, mirrors `InvoicePdfGenerationTest`.
- `PaymentReceiptMailTest` — `PaymentMailer::sendReceipt` queues mail to contacts with email, sets `emailed_at`; `notifyAdmins` queues to all `is_admin` users, sets `admin_notified_at`.
- `MarkInvoiceAsPaidTest` (Filament resource test) — calling the mark-as-paid action: creates a `Payment`, flips invoice status, generates the PDF, sends client email only when contacts selected, always notifies admins.
- `VoidPaymentTest` — voiding excludes the payment from `active()`/exports, reopens the invoice to `Sent` or `Overdue` based on due date, sets `void_reason`.

## Open items for the implementation plan to resolve

None — all decisions needed to start were made during design (correction handling: void; payment methods: Cash/Check/ACH/Card/Other; receipt recipients: selectable per payment).
