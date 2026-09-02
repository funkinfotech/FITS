<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringChargeModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_inactive_charge_is_never_due(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);

        $charge = RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-01-01',
            'is_active' => false,
        ]);

        $this->assertFalse($charge->isDue(Carbon::parse('2027-01-01')));
    }

    public function test_charge_is_not_due_after_its_end_date(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);

        $charge = RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-06-01',
        ]);

        $this->assertFalse($charge->isDue(Carbon::parse('2026-07-01')));
    }

    public function test_company_balance_owed_sums_only_sent_and_overdue_invoices(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);

        $sent = Invoice::create(['company_id' => $company->id]);
        $sent->lineItems()->create(['description' => 'A', 'quantity' => 1, 'unit_price' => 100]);
        $sent->recalculateTotal()->save();
        $sent->update(['status' => InvoiceStatus::Sent]);

        $overdue = Invoice::create(['company_id' => $company->id]);
        $overdue->lineItems()->create(['description' => 'B', 'quantity' => 1, 'unit_price' => 50]);
        $overdue->recalculateTotal()->save();
        $overdue->update(['status' => InvoiceStatus::Overdue]);

        $paid = Invoice::create(['company_id' => $company->id]);
        $paid->lineItems()->create(['description' => 'C', 'quantity' => 1, 'unit_price' => 999]);
        $paid->recalculateTotal()->save();
        $paid->update(['status' => InvoiceStatus::Paid]);

        $this->assertSame('150.00', $company->fresh()->balance_owed);
    }
}
