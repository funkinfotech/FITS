# Client Portal Invoices & Receipts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give portal clients a self-serve "Invoices" section: a list of their company's invoices with a balance-owed summary, an invoice detail page with line items, and — once an invoice is paid — a link to download its payment receipt.

**Architecture:** A new portal-side `InvoiceController` (matching the existing `TicketController`'s style and namespace) backed by a new `InvoicePolicy` that scopes everything to `$user->company_id`. Two new Blade views follow the existing `dashboard.blade.php` / `tickets/show.blade.php` conventions exactly (same layout, same badge/card/pagination patterns). No new tables or migrations — this reads the existing `invoices`/`payments` tables the admin side already writes to.

**Tech Stack:** Laravel 12, Blade + Livewire (`wire:navigate`) portal layout, Tailwind (via a custom plugin-based badge-class system already used for ticket status/priority), PHPUnit (`Tests\TestCase` + `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-09-21-client-portal-invoices-design.md`

## Global Constraints

- Read-only. No payment collection, no "Pay Now" button, no payment-processor integration.
- No separate "Payments" / transaction-history page — a paid invoice's receipt is linked from that invoice's own detail page.
- Authorization is strictly by `company_id` — `Invoice` has no per-user ownership concept (unlike `Ticket`, which also allows `user_id`-only ownership). A user with `company_id = null` sees an empty invoices list, not an error page.
- `InvoiceStatus` has exactly 5 cases today: `Draft`, `Sent`, `Overdue`, `Paid`, `Void`.
- New route names must not collide with the existing admin-only `invoices.download` / `payments.download` route names (`routes/web.php`, gated by `EnsureUserIsAdmin`) — those are untouched.
- Every money value in a new view is wrapped in `number_format(..., 2)`, matching this codebase's existing convention in `resources/views/pdfs/invoice.blade.php` and `dashboard.blade.php` (even where the underlying value is already a `decimal:2`-cast string) — don't "simplify" this away, it's the established house style.
- This plan touches `tailwind.config.js`. **The deploy after this feature needs `npm run build`** — unlike the last several PHP-only deploys, the compiled CSS on the server must be regenerated or the new badge classes render unstyled.

---

### Task 1: `Invoice::payments()` relation, `InvoiceStatus::colorClass()`, Tailwind invoice badge classes

**Files:**
- Modify: `app/Models/Invoice.php` (add `payments()` relation)
- Modify: `app/Enums/InvoiceStatus.php` (add `colorClass()` method)
- Modify: `tailwind.config.js` (5 new badge classes)
- Modify: `tests/Feature/InvoiceModelTest.php` (add 2 test methods)

**Interfaces:**
- Produces: `Invoice::payments(): HasMany` (returns all `Payment` rows for the invoice, active or voided — callers filter with `->active()` themselves, matching how `Payment::scopeActive()` is used everywhere else in this codebase); `InvoiceStatus::colorClass(): string` (returns `'badge badge-invoice-{draft|sent|overdue|paid|void}'`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/InvoiceModelTest.php` (inside the existing `InvoiceModelTest` class, after `test_line_item_amount_is_always_server_computed`):

```php
    public function test_payments_relation_returns_the_invoices_payments(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);

        $this->assertTrue($invoice->payments->contains($payment));
    }

    public function test_status_color_class_returns_a_distinct_badge_for_each_status(): void
    {
        $classes = collect(InvoiceStatus::cases())->map(fn ($status) => $status->colorClass());

        $this->assertSame($classes->count(), $classes->unique()->count());
        $this->assertTrue($classes->every(fn ($class) => str_starts_with($class, 'badge badge-invoice-')));
    }
```

Add the two new imports to the top of the file (alongside the existing `use App\Models\Company;` / `use App\Models\Invoice;`):

```php
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoiceModelTest`
Expected: the two new tests FAIL — `payments()` doesn't exist (`Call to undefined method`), and `colorClass()` doesn't exist either.

- [ ] **Step 3: Add the `payments()` relation to `Invoice`**

In `app/Models/Invoice.php`, add this method alongside the other relations (`company()`, `recurringCharge()`, `lineItems()`, `creator()`):

```php
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
```

Add `use App\Models\Payment;` is not needed — `Payment` is in the same `App\Models` namespace as `Invoice`, so no import is required (consistent with how `RecurringCharge`, `InvoiceLineItem`, and `User` are referenced unqualified in this same file's other relation methods).

- [ ] **Step 4: Add `colorClass()` to `InvoiceStatus`**

In `app/Enums/InvoiceStatus.php`, add this method alongside the existing `filamentColor()`:

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

- [ ] **Step 5: Add the 5 new badge classes to `tailwind.config.js`**

Three separate edits to the same file:

**5a. Add to the `safelist` array**, alongside the existing `'badge-status-closed',` entry:

```js
        'badge-invoice-draft',
        'badge-invoice-sent',
        'badge-invoice-overdue',
        'badge-invoice-paid',
        'badge-invoice-void',
```

**5b. Add to `theme.extend.colors`**, alongside the existing `'status-closed': '#6b7280',` entry:

```js
                'invoice-draft': '#6b7280',    // gray-500
                'invoice-sent': '#0ea5e9',     // sky-500
                'invoice-overdue': '#ef4444',  // red-500
                'invoice-paid': '#22c55e',     // green-500
                'invoice-void': '#78716c',     // stone-500
```

**5c. Add to the `plugin(function({ addComponents, theme }) {...})` block's `addComponents({...})` call**, alongside the existing `'.badge-status-closed': {...},` entry:

```js
          '.badge-invoice-draft': {
            color: theme('colors.invoice-draft'),
            borderColor: theme('colors.invoice-draft'),
            backgroundColor: '#6b72801A',
          },
          '.badge-invoice-sent': {
            color: theme('colors.invoice-sent'),
            borderColor: theme('colors.invoice-sent'),
            backgroundColor: '#0ea5e91A',
          },
          '.badge-invoice-overdue': {
            color: theme('colors.invoice-overdue'),
            borderColor: theme('colors.invoice-overdue'),
            backgroundColor: '#ef44441A',
          },
          '.badge-invoice-paid': {
            color: theme('colors.invoice-paid'),
            borderColor: theme('colors.invoice-paid'),
            backgroundColor: '#22c55e1A',
          },
          '.badge-invoice-void': {
            color: theme('colors.invoice-void'),
            borderColor: theme('colors.invoice-void'),
            backgroundColor: '#78716c1A',
          },
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoiceModelTest`
Expected: PASS (5 tests total — the 3 pre-existing plus the 2 new ones).

- [ ] **Step 7: Verify the Tailwind config is syntactically valid**

Run: `node -e "require('./tailwind.config.js')"` — if this errors (e.g. `require is not defined in ES module scope` because the file uses `export default`), instead run `npx tailwindcss -i resources/css/app.css -o /tmp/tailwind-check.css --content './resources/**/*.blade.php'` and confirm it completes without throwing. Either way, the goal is just confirming the JS is well-formed after your edits — do not commit a broken build config.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Invoice.php app/Enums/InvoiceStatus.php tailwind.config.js tests/Feature/InvoiceModelTest.php
git commit -m "Add Invoice::payments() relation, InvoiceStatus::colorClass(), and invoice badge CSS"
```

---

### Task 2: `InvoicePolicy`

**Files:**
- Create: `app/Policies/InvoicePolicy.php`
- Test: `tests/Feature/InvoicePortalAccessTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`company_id` column, already exists), `App\Models\Invoice` (`company_id` column, already exists).
- Produces: `InvoicePolicy::view(User $user, Invoice $invoice): bool`, auto-discovered by Laravel's default policy naming convention (`App\Policies\{Model}Policy` for `App\Models\{Model}`) — the same mechanism `TicketPolicy` already relies on with no explicit registration anywhere in this codebase. Later tasks call this via `$this->authorize('view', $invoice)` in the portal controller, and directly via `$user->can('view', $invoice)` in tests.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_in_the_same_company_can_view_the_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertTrue($user->can('view', $invoice));
    }

    public function test_coworker_at_the_same_company_can_view_the_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $someoneElse = User::factory()->create(['company_id' => $company->id]);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        unset($someoneElse);

        $this->assertTrue($coworker->can('view', $invoice));
    }

    public function test_user_at_a_different_company_cannot_view_the_invoice(): void
    {
        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $user = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);

        $this->assertFalse($user->can('view', $invoice));
    }

    public function test_user_with_no_company_cannot_view_any_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => null]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertFalse($user->can('view', $invoice));
    }

    public function test_an_admin_can_view_any_invoice_regardless_of_company(): void
    {
        // This policy is auto-discovered globally, not scoped to the portal — Filament's
        // admin InvoiceResource has no custom canView() override, so it defers to this same
        // policy. Admin users have no company_id, so without this bypass every admin would
        // be locked out of every invoice in the admin panel too.
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => null]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertTrue($admin->can('view', $invoice));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalAccessTest`
Expected: FAIL — with no policy registered, `$user->can('view', $invoice)` returns `false` unconditionally (Laravel's default "deny if no policy/gate matches" behavior), so the same-company and coworker tests fail their `assertTrue` (the different-company and no-company tests would coincidentally "pass" already, since the default is deny — but run the whole file, don't cherry-pick, since the point is confirming the policy doesn't exist yet).

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->is_admin
            || ($user->company_id !== null && $invoice->company_id === $user->company_id);
    }
}
```

**Correction (found while running Task 5's full-suite regression check):** this policy is auto-discovered globally by Laravel — it is not scoped to the portal. Filament's admin `InvoiceResource` has no custom `canView()`/authorization override, so it defers to this same policy for its own access checks. Admin users are staff, not tied to any one client company (`company_id` is null for them), so without the `$user->is_admin ||` bypass, every admin would be locked out of every invoice in the admin panel — this broke `MarkInvoiceAsPaidFromViewPageTest` (an *admin*-side test, unrelated to this plan on its face) the moment this policy was auto-discovered. The bypass exactly mirrors the one already established in `TicketPolicy::view()` (`$user->is_admin || $this->belongsToTicket(...)`), which this policy should have matched from the start.

- [ ] **Step 4: Run test to verify it passes**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalAccessTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Policies/InvoicePolicy.php tests/Feature/InvoicePortalAccessTest.php
git commit -m "Add InvoicePolicy scoping portal invoice access to the user's company"
```

---

### Task 3: Invoice list page (`InvoiceController::index`, route, view, nav link)

**Files:**
- Create: `app/Http/Controllers/InvoiceController.php` (with `index()` only — `show()` comes in Task 4)
- Modify: `routes/web.php` (add the `invoices.index` route)
- Create: `resources/views/invoices/index.blade.php`
- Modify: `resources/views/livewire/layout/navigation.blade.php` (add "Invoices" nav link, desktop + mobile)
- Test: `tests/Feature/InvoicePortalPageTest.php`

**Interfaces:**
- Consumes: `Invoice::payments()`, `InvoiceStatus::colorClass()` (Task 1); `Company::getBalanceOwedAttribute()` (existing, `app/Models/Company.php` — already returns a `number_format`-ed string like `"150.00"`).
- Produces: route `invoices.index` (`GET /invoices`), view `invoices.index` rendering an `$invoices` paginator and a `$balanceOwed` string.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePortalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_users_own_company_invoices(): void
    {
        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $user = User::factory()->create(['company_id' => $companyA->id]);

        $ownInvoice = Invoice::create(['company_id' => $companyA->id]);
        $otherInvoice = Invoice::create(['company_id' => $companyB->id]);

        $response = $this->actingAs($user)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee($ownInvoice->invoice_number);
        $response->assertDontSee($otherInvoice->invoice_number);
    }

    public function test_index_shows_the_companys_balance_owed(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->recalculateTotal()->save();
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('250.00');
    }

    public function test_index_shows_an_empty_state_for_a_user_with_no_company(): void
    {
        $user = User::factory()->create(['company_id' => null]);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('have any invoices yet')
            ->assertSee('0.00');
    }

    public function test_coworkers_at_the_same_company_see_the_same_invoices(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $userA = User::factory()->create(['company_id' => $company->id]);
        $userB = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->actingAs($userB)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number);

        unset($userA);
    }

    public function test_a_user_with_no_company_never_sees_an_invoice_with_a_null_company_id(): void
    {
        // Regression test: Invoice::where('company_id', $user->company_id) with a null
        // company_id compiles to "WHERE company_id IS NULL" in SQL, not "matches nothing" —
        // so without the explicit ternary in the controller, a no-company user would see any
        // invoice whose company_id happened to be null too (e.g. an orphaned invoice left
        // behind by a deleted company, since invoices.company_id is nullOnDelete()).
        $company = Company::create(['name' => 'Acme Corp']);
        $orphanInvoice = Invoice::create(['company_id' => $company->id]);
        $orphanInvoice->update(['company_id' => null]);

        $user = User::factory()->create(['company_id' => null]);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertDontSee($orphanInvoice->invoice_number);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalPageTest`
Expected: FAIL — `route('invoices.index')` doesn't exist (`RouteNotFoundException`).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = $user->company_id
            ? Invoice::where('company_id', $user->company_id)->latest('issue_date')->paginate(15)
            : Invoice::whereRaw('1 = 0')->paginate(15);

        return view('invoices.index', [
            'invoices' => $invoices,
            'balanceOwed' => $user->company?->balance_owed ?? '0.00',
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, add these two lines inside the existing `Route::middleware(['auth'])->group(function () { ... })` block, after the `attachments.show` route and before the closing `});`:

```php
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
```

The second line is registered now even though `InvoiceController::show()` isn't written until Task 4 — the view below links every invoice row to `route('invoices.show', $invoice)`, and Laravel's `route()` helper only needs the route *name* to exist to generate a URL; it doesn't check that the controller method exists until the route is actually requested. This task's tests render the index page (which generates those URLs for each row) but never follow them, so this is safe — the same pattern used between Tasks 4 and 5 for `invoices.pdf`/`invoices.receipt`.

Add the import alongside the other controller `use` statements at the top of the file:

```php
use App\Http\Controllers\InvoiceController;
```

- [ ] **Step 5: Write the view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-10 px-6">
    <h1 class="text-3xl font-bold mb-6 text-primary">Invoices</h1>

    <div class="mb-8 bg-white shadow rounded p-6">
        <p class="text-sm text-gray-500">Total balance due</p>
        <p class="text-3xl font-bold text-primary">${{ number_format((float) $balanceOwed, 2) }}</p>
    </div>

    @if ($invoices->isEmpty())
        <p class="text-gray-600">You don't have any invoices yet.</p>
    @else
        <div class="bg-white shadow rounded divide-y">
            @foreach ($invoices as $invoice)
                <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="block hover:bg-gray-50 transition rounded-md">
                    <div class="p-4 border-b">
                        <div class="flex justify-between items-center gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900">
                                    Invoice #{{ $invoice->invoice_number }}
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Issued {{ $invoice->issue_date?->format('M j, Y') }} &middot; Due {{ $invoice->due_date?->format('M j, Y') }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-block rounded-md px-2 py-1 text-xs font-semibold {{ $invoice->status->colorClass() }}">
                                    {{ $invoice->status->value }}
                                </span>
                                <span class="text-sm font-semibold text-gray-900">${{ number_format((float) $invoice->total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($invoices->hasPages())
            <div class="mt-6">{{ $invoices->links() }}</div>
        @endif
    @endif
</div>
@endsection
```

- [ ] **Step 6: Add the nav link**

In `resources/views/livewire/layout/navigation.blade.php`, add a desktop link immediately after the existing Dashboard `<x-nav-link>` (inside the `<div class="hidden space-x-8 ...">` block):

```blade
                    <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" wire:navigate>
                        {{ __('Invoices') }}
                    </x-nav-link>
```

And a matching mobile link immediately after the existing Dashboard `<x-responsive-nav-link>` (inside the `<div class="pt-2 pb-3 space-y-1">` block):

```blade
            <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" wire:navigate>
                {{ __('Invoices') }}
            </x-responsive-nav-link>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalPageTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php routes/web.php resources/views/invoices/index.blade.php resources/views/livewire/layout/navigation.blade.php tests/Feature/InvoicePortalPageTest.php
git commit -m "Add portal invoice list page with balance-owed summary"
```

---

### Task 4: Invoice detail page (`InvoiceController::show`, route, view)

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (add `show()`)
- Modify: `routes/web.php` (add the `invoices.show` route)
- Create: `resources/views/invoices/show.blade.php`
- Modify: `tests/Feature/InvoicePortalPageTest.php` (add show-page tests)

**Interfaces:**
- Consumes: `InvoicePolicy::view()` (Task 2), `Invoice::payments()` + `Payment::scopeActive()` (Task 1 / existing) to find the receipt.
- Produces: route `invoices.show` (`GET /invoices/{invoice}`), view `invoices.show` rendering `$invoice` (with `lineItems` loaded) and `$receipt` (a `Payment|null`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/InvoicePortalPageTest.php` (inside the existing class), and add `use App\Enums\PaymentMethod;` and `use App\Models\Payment;` to its imports:

```php
    public function test_show_page_displays_line_items_status_and_dates(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100]);
        $invoice->recalculateTotal()->save();
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Consulting');
        $response->assertSee('Sent');
        $response->assertSee('200.00');
    }

    public function test_show_page_links_to_the_receipt_when_the_invoice_is_paid(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('Download Receipt');
    }

    public function test_show_page_has_no_receipt_section_when_unpaid(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Download Receipt');
    }

    public function test_user_at_a_different_company_gets_403_on_show(): void
    {
        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);

        $this->actingAs($outsider)
            ->get(route('invoices.show', $invoice))
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalPageTest`

Expected: FAIL — but not with a routing error this time. Task 3 already registered the `invoices.show` *route* (it had to, so the index page's row links would resolve), but `InvoiceController::show()` doesn't exist yet, so every test that requests `route('invoices.show', $invoice)` gets a fatal `Error: Call to undefined method App\Http\Controllers\InvoiceController::show()` — PHPUnit reports this as an error, not a clean assertion failure, but it's still the expected RED state: these tests fail before this step's implementation and should pass after it.

- [ ] **Step 3: Add `show()` to the controller**

In `app/Http/Controllers/InvoiceController.php`, add this method after `index()`:

```php
    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load('lineItems');
        $receipt = $invoice->payments()->active()->first();

        return view('invoices.show', compact('invoice', 'receipt'));
    }
```

- [ ] **Step 4: Add the remaining routes**

`invoices.show` was already registered in Task 3 (needed there so the index page's row links would resolve) — do not re-register it, that would raise a duplicate-route-name conflict. In `routes/web.php`, add these two lines in the same `auth` middleware group, directly after the `invoices.show` route:

```php
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'downloadReceipt'])->name('invoices.receipt');
```

These are registered now even though `downloadPdf`/`downloadReceipt` aren't written until Task 5 — the view below links to both (`route('invoices.pdf', ...)`, `route('invoices.receipt', ...)`), and Laravel's `route()` helper only needs the route *name* to exist to generate a URL; it doesn't check that the controller method exists until the route is actually requested. None of this task's tests request those URLs, so this is safe — Task 5 fills in the two methods behind routes that already resolve.

- [ ] **Step 5: Write the view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto pt-12 py-10 px-6">

    <a href="{{ route('invoices.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
        &larr; Back to invoices
    </a>

    <div class="mt-4 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">Invoice #{{ $invoice->invoice_number }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $invoice->from_business_name }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Issued {{ $invoice->issue_date?->format('M j, Y') }} &middot; Due {{ $invoice->due_date?->format('M j, Y') }}
                </p>
            </div>

            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $invoice->status->colorClass() }}">
                {{ $invoice->status->value }}
            </span>
        </div>

        <table class="w-full mt-6 text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Description</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 text-right">Unit Price</th>
                    <th class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lineItems as $item)
                    <tr class="border-b">
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ number_format($item->quantity, 2) }}</td>
                        <td class="py-2 text-right">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="py-2 text-right">${{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold">
                    <td colspan="3" class="py-2 text-right">Total</td>
                    <td class="py-2 text-right">${{ number_format((float) $invoice->total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('invoices.pdf', $invoice) }}"
               class="inline-block px-5 py-3 bg-primary text-white font-semibold rounded shadow hover:bg-opacity-90">
                Download PDF
            </a>
        </div>

        @if ($receipt)
            <div class="mt-6 border-t pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Receipt</h2>
                <p class="text-sm text-gray-600">
                    Receipt #{{ $receipt->receipt_number }} &middot; Paid {{ $receipt->paid_date?->format('M j, Y') }} via {{ $receipt->method->value }}
                </p>
                <a href="{{ route('invoices.receipt', $invoice) }}"
                   class="mt-3 inline-block px-5 py-3 bg-primary text-white font-semibold rounded shadow hover:bg-opacity-90">
                    Download Receipt
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalPageTest`
Expected: PASS (8 tests total — the 4 from Task 3 plus the 4 new ones).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php routes/web.php resources/views/invoices/show.blade.php tests/Feature/InvoicePortalPageTest.php
git commit -m "Add portal invoice detail page with line items and receipt link"
```

---

### Task 5: PDF and receipt downloads (`downloadPdf`, `downloadReceipt`)

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (add `downloadPdf()` and `downloadReceipt()` — the routes for these already exist from Task 4's Step 6)
- Modify: `tests/Feature/InvoicePortalAccessTest.php` (add download tests)

**Interfaces:**
- Consumes: `InvoicePolicy::view()` (Task 2), `Invoice::payments()` + `Payment::scopeActive()` (Task 1), the existing `Storage::disk('local')->download(...)` pattern already used by `PaymentDownloadController`/`InvoiceDownloadController`.
- Produces: working `invoices.pdf` and `invoices.receipt` routes (previously registered but unimplemented).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/InvoicePortalAccessTest.php` (inside the existing class), and add these imports: `use App\Enums\PaymentMethod;`, `use App\Models\Payment;`, `use Illuminate\Support\Facades\Storage;`:

```php
    public function test_user_can_download_their_own_invoice_pdf(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->forceFill(['pdf_path' => 'invoices/2026/INV-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($invoice->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk();
    }

    public function test_invoice_pdf_download_404s_when_no_pdf_has_been_generated(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertNotFound();
    }

    public function test_user_at_a_different_company_cannot_download_the_invoice_pdf(): void
    {
        Storage::fake('local');

        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);
        $invoice->forceFill(['pdf_path' => 'invoices/2026/INV-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($invoice->pdf_path, 'fake-pdf-contents');

        $this->actingAs($outsider)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_user_can_download_the_receipt_for_a_paid_invoice(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk();
    }

    public function test_receipt_download_404s_when_the_invoice_has_no_payment(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertNotFound();
    }

    public function test_receipt_download_404s_when_the_only_payment_is_voided(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill([
            'pdf_path' => 'payments/2026/RCPT-2026-0001.pdf',
            'voided_at' => now(),
        ])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertNotFound();
    }

    public function test_user_at_a_different_company_cannot_download_the_receipt(): void
    {
        Storage::fake('local');

        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($outsider)
            ->get(route('invoices.receipt', $invoice))
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalAccessTest`
Expected: FAIL — `downloadPdf`/`downloadReceipt` don't exist on `InvoiceController` (`Target class [InvoiceController] has no method [downloadPdf]`, surfaced as a 500 rather than a clean assertion failure).

- [ ] **Step 3: Add the two download methods**

In `app/Http/Controllers/InvoiceController.php`, add these methods after `show()`, and add `use Illuminate\Support\Facades\Storage;` to the file's imports:

```php
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

- [ ] **Step 4: Run tests to verify they pass**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=InvoicePortalAccessTest`
Expected: PASS (11 tests total — the 4 from Task 2 plus the 7 new ones).

- [ ] **Step 5: Run the full Invoice-related regression filter**

Run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit --filter=Invoice`
Expected: every Invoice-related test across the whole app passes — this plan's new tests plus the pre-existing `InvoiceModelTest`, `InvoiceResourceTest`, `InvoiceMailTest`, `InvoicePdfGenerationTest`, `InvoiceNumberGeneratorTest`, `InvoiceOverdueActionsTest`, `MarkInvoiceAsPaidTest`, `MarkInvoiceAsPaidFromViewPageTest`, `MarkOverdueInvoicesTest`, `NotifyAdminsOfOverdueInvoicesTest`, `GenerateRecurringInvoicesTest` — confirming nothing in the admin side regressed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/InvoicePortalAccessTest.php
git commit -m "Add portal invoice PDF and receipt download endpoints"
```

---

## Final verification

- [ ] Run the entire suite: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=512M vendor/bin/phpunit` — expect zero failures.
- [ ] Run `npm install && npm run build` and confirm it completes without error — this is the first deploy in a while that needs it (Task 1 touches `tailwind.config.js`).
- [ ] Manually click through in the browser: log in as a portal user with a company that has invoices → click "Invoices" in the nav → confirm the balance-owed figure and invoice list look right → open an invoice → confirm line items/status/dates render → download the PDF → if the invoice is paid, confirm the receipt section appears and downloads correctly → log in as a user from a *different* company and confirm they cannot reach another company's invoice by guessing its URL (should 403).
