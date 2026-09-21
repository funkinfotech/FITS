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
}
