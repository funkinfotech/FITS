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
