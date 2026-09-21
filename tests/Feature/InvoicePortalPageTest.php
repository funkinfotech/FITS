<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
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
}
