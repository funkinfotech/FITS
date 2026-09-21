# Client Portal Invoices & Receipts — Design

Date: 2026-09-21
Status: Approved by user, ready for implementation planning

## Problem

The client-facing portal (as opposed to the Filament admin panel) currently only has a ticket dashboard and ticket detail pages. Now that invoicing and payment receipts exist on the admin side, clients have no way to see their own invoices or download a receipt after paying — they have to ask support or wait for an emailed PDF. The portal needs an "Invoices" section clients can self-serve from.

## Goals

- A client can see a list of their company's invoices with status and amount.
- A client can see how much their company currently owes, at a glance.
- A client can open an individual invoice and see its line items, status, and dates.
- A client can download the invoice PDF.
- If an invoice is paid, the client can download the payment receipt from that same page.
- All of this is scoped strictly to the logged-in user's own company — no cross-company visibility.

## Non-goals (explicitly out of scope for this iteration)

- **No payment collection.** Read-only — view and download only. No "Pay Now" button, no payment processor integration. (Confirmed with the user.)
- **No separate "Payments" / transaction-history page.** This app's invoice→payment relationship is one active payment per invoice (see the payment-receipts feature's own non-goals — no partial payments). A standalone payments list would just re-list the same paid invoices a second time. The receipt lives on the invoice it pays, which is where clients will look for it. (Confirmed with the user — industry billing portals only split payments into their own view when payments and invoices can diverge, e.g. partial/multiple payments per invoice or subscriptions; neither applies here.)
- **No invoice editing, disputing, or commenting** from the portal.
- **No changes to the admin-side Invoice/Payment Filament resources** — this is purely additive, a new portal surface reading the same tables.

## Data model changes

Two small additions, no migrations:

### `Invoice::payments()` relation (new)

Today only `Payment belongsTo Invoice` exists; `Invoice` has no inverse relation. Add:

```php
public function payments()
{
    return $this->hasMany(Payment::class);
}
```

Used by the invoice show page to find the receipt to link to: `$invoice->payments()->active()->first()`.

### `InvoiceStatus::colorClass()` (new enum method)

Mirrors the existing `TicketStatus::colorClass()` / `TicketPriority::colorClass()` pattern exactly (`app/Enums/TicketStatus.php`), which returns a Tailwind-plugin-defined badge class consumed directly in Blade (`class="... {{ $status->colorClass() }}"`). `InvoiceStatus` currently only has `filamentColor()` (for the admin panel's Filament badge column) — that stays untouched; this is a new, separate method for the portal's plain-HTML badges.

```php
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
```

(`InvoiceStatus` has exactly 5 cases today: `Draft`, `Sent`, `Overdue`, `Paid`, `Void` — confirmed against the current enum file, no `PartiallyPaid`/`RolledOver` case exists.)

### Tailwind badge classes (new)

`tailwind.config.js` defines the existing ticket badges via a `plugin(function ({ addComponents, theme }) { ... })` block, each color also declared in `theme.extend.colors` and each class name added to the top-level `safelist` array (required because these class names are built dynamically in PHP, not present as literal strings anywhere Tailwind's content scanner looks). Five new invoice status badges need the same three-part treatment (safelist entry + `theme.extend.colors` entry + `addComponents` entry), following the exact existing pattern:

| Status | Suggested base color | Rationale |
|---|---|---|
| Draft | `#6b7280` (gray-500) | Matches ticket "closed" gray — inactive/not sent yet |
| Sent | `#0ea5e9` (sky-500) | Matches ticket "open" blue — active, awaiting payment |
| Overdue | `#ef4444` (red-500) | Urgent — matches Filament's `danger` mapping |
| Paid | `#22c55e` (green-500) | Success — matches ticket "in progress" green tone family |
| Void | `#78716c` (stone-500) | Filament maps this to `danger` too (same as Overdue), but visually conflating "cancelled" with "urgent" would confuse a client glancing at the badge. Deliberately a different muted tone than Draft's gray-500 so the two "inactive" statuses stay visually distinguishable from each other, not just from Overdue |

**Because this touches `tailwind.config.js`, the next deploy after this feature needs `npm run build`** (unlike the last several PHP-only deploys) — the compiled CSS on the server must be regenerated or the new badge classes won't render (they'll just have no styling).

## Authorization

### `InvoicePolicy` (new)

Mirrors `TicketPolicy` (`app/Policies/TicketPolicy.php`), but simpler — `Invoice` has no `user_id`, only `company_id`, so there's no "owns it directly" branch to check:

```php
<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id !== null && $invoice->company_id === $user->company_id;
    }
}
```

Laravel auto-discovers this by naming convention (`App\Policies\{Model}Policy` for `App\Models\{Model}`) — the same mechanism `TicketPolicy` relies on already (no explicit registration found anywhere for it), so no `AuthServiceProvider` change is needed.

### User with no company

A portal `User` can have `company_id = null` (confirmed in `User` model). Since `Invoice` has no per-user ownership concept at all (unlike `Ticket`, which falls back to `user_id`), such a user genuinely has zero invoices, ever. Rather than special-casing this with a 403 or hiding the nav link conditionally, the index page shows an empty state — the same way the ticket dashboard already handles "zero tickets" gracefully. Simpler, and consistent with how the rest of the portal degrades.

## Controller & routes

### `App\Http\Controllers\InvoiceController` (new, portal-facing)

Naming matches the existing portal `TicketController` convention (not prefixed "Portal") since it lives in the same namespace serving the same audience.

```php
public function index(Request $request)
{
    $user = $request->user();

    $invoices = Invoice::where('company_id', $user->company_id) // null-safe: no rows match a null company_id
        ->latest('issue_date')
        ->paginate(15);

    return view('invoices.index', [
        'invoices' => $invoices,
        'balanceOwed' => $user->company?->balance_owed ?? '0.00',
    ]);
}

public function show(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    $invoice->load('lineItems');
    $receipt = $invoice->payments()->active()->first();

    return view('invoices.show', compact('invoice', 'receipt'));
}

public function downloadPdf(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

    return Storage::disk('local')->download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
}

public function downloadReceipt(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    $receipt = $invoice->payments()->active()->first();

    abort_unless($receipt && $receipt->pdf_path && Storage::disk('local')->exists($receipt->pdf_path), 404);

    return Storage::disk('local')->download($receipt->pdf_path, "{$receipt->receipt_number}.pdf");
}
```

`Invoice::where('company_id', $user->company_id)` with a null `company_id` matches zero rows in SQL (`= NULL` is never true) — correctly produces an empty list without needing an explicit null guard, consistent with the "empty state, not an error" decision above.

### Routes (added to the existing `auth`-middleware group in `routes/web.php`)

```php
Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'downloadReceipt'])->name('invoices.receipt');
```

Route names are deliberately distinct from the existing admin-only `invoices.download` / `payments.download` names (`routes/web.php`, gated by `EnsureUserIsAdmin`) to avoid any collision — those stay exactly as they are.

## Views

Both new views follow the existing portal conventions exactly (`@extends('layouts.app')` / `@section('content')`, `max-w-*-xl mx-auto py-10 px-6` container, `bg-white shadow rounded` cards, the `primary` Tailwind color palette, `${{ number_format($x, 2) }}` for money since no shared currency helper exists anywhere in this app yet, `->inDisplayTz()->format('F j, Y g:i A')` for dates).

### `resources/views/invoices/index.blade.php`

Modeled directly on `resources/views/dashboard.blade.php`'s ticket list:
- A "Total balance due" summary card at the top (uses `$balanceOwed` — hidden or shows `$0.00` if nothing owed, not an error state).
- A `bg-white shadow rounded divide-y` list of invoice rows (mirroring the ticket list's card-per-row pattern), each showing invoice number, issue/due date, total, and a status badge (`{{ $invoice->status->colorClass() }}`), linking to `invoices.show`.
- Empty state ("You don't have any invoices yet.") when the paginated collection is empty — no need for the ticket dashboard's tabbed active/closed complexity, since there's no "closed" analog planned for invoices in this iteration.
- `{{ $invoices->links() }}` pagination, matching the dashboard's `@if ($tickets->hasPages())` pattern.

### `resources/views/invoices/show.blade.php`

Modeled on `resources/views/tickets/show.blade.php`'s header-card pattern:
- Back link to `invoices.index`.
- Header card: invoice number, status badge, issue/due dates.
- Line items table (description, quantity, unit price, amount — same columns as the admin/PDF versions).
- Total.
- "Download PDF" button (`invoices.pdf`), always visible.
- If `$receipt` is present: a "Payment Receipt" section (receipt number, paid date, method) with a "Download Receipt" button (`invoices.receipt`). If not present (invoice not yet paid), this section doesn't render — no empty placeholder needed.

### Navigation (`resources/views/livewire/layout/navigation.blade.php`)

Add one `<x-nav-link>` (desktop) and one `<x-responsive-nav-link>` (mobile) pair for "Invoices", copying the existing "Dashboard" link's exact markup/placement, active state via `request()->routeIs('invoices.*')` (matches both index and show pages), pointing at `route('invoices.index')`.

## Testing plan

Mirrors this app's existing portal test conventions (`tests/Feature/TicketAccessTest.php`, `tests/Feature/PortalTicketPageTest.php`, `tests/Feature/DashboardTest.php`) — plain PHPUnit `TestCase` + `RefreshDatabase`, direct model creation, `actingAs($user)->get(route(...))`.

- **`tests/Feature/InvoicePortalAccessTest.php`** — authorization, mirroring `TicketAccessTest`'s structure:
  - A user views an invoice belonging to their own company → 200.
  - A user attempts to view an invoice belonging to a *different* company → 403.
  - A user with `company_id = null` attempts to view any invoice → 403.
  - The index page for a user with `company_id = null` → 200, empty list, `$0.00` balance (not an error).
  - Two users in the same company can both see the same company's invoices (company-wide visibility, matching how tickets already work for coworkers).

- **`tests/Feature/InvoicePortalPageTest.php`** — page content/rendering:
  - Index page lists only the user's own company's invoices (not other companies'), shows the correct balance-owed figure, correct status badges.
  - Show page displays line items, status, dates correctly for an invoice with line items.
  - Show page's "Download Receipt" section is present when the invoice has an active payment, absent when it doesn't.
  - Downloading the invoice PDF: `Storage::fake('local')` + a pre-set `pdf_path` → `assertOk()`; missing `pdf_path` → `assertNotFound()` (mirrors `PaymentDownloadControllerTest`'s existing pattern exactly, just scoped by company instead of by admin role).
  - Downloading the receipt: present + `Storage::fake('local')` with the payment's `pdf_path` set → `assertOk()`; invoice with no payment → `assertNotFound()`; invoice with only a *voided* payment → `assertNotFound()` (proves `->active()` filtering is applied, not just "any payment exists").
  - Cross-company download attempts (PDF and receipt) → `assertForbidden()`.

## Open items for the implementation plan to resolve

None — all decisions needed to start were made during design (scope: invoices + balance summary, no separate payments page; read-only, no payment CTA; dedicated "Invoices" nav item and pages).
