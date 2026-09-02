<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_invoice_populates_defaults_and_snapshots(): void
    {
        Carbon::setTestNow('2026-07-29');

        BusinessProfile::current()->update([
            'business_name' => 'FunkIT HelpDesk',
            'address' => '1 FunkIT Way',
            'default_net_days' => 15,
            'default_terms_text' => 'Payment due within 15 days.',
        ]);

        $company = Company::create(['name' => 'Acme Corp', 'address' => '42 Acme Ave']);

        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertSame('INV-2026-0001', $invoice->invoice_number);
        $this->assertSame(2026, $invoice->year);
        $this->assertSame(1, $invoice->sequence);
        $this->assertSame('2026-07-29', $invoice->issue_date->toDateString());
        $this->assertSame('2026-08-13', $invoice->due_date->toDateString());
        $this->assertSame('Acme Corp', $invoice->bill_to_name);
        $this->assertSame('42 Acme Ave', $invoice->bill_to_address);
        $this->assertSame('FunkIT HelpDesk', $invoice->from_business_name);
        $this->assertSame('1 FunkIT Way', $invoice->from_address);
        $this->assertSame('Payment due within 15 days.', $invoice->terms);

        Carbon::setTestNow();
    }

    public function test_recalculate_total_sums_line_items(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100]);
        $invoice->lineItems()->create(['description' => 'Support', 'quantity' => 1, 'unit_price' => 50.5]);

        $invoice->recalculateTotal()->save();

        $this->assertSame('250.50', $invoice->fresh()->total);
    }

    public function test_line_item_amount_is_always_server_computed(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $item = $invoice->lineItems()->create([
            'description' => 'Consulting',
            'quantity' => 3,
            'unit_price' => 10,
            'amount' => 999999,
        ]);

        $this->assertSame('30.00', $item->fresh()->amount);
    }
}
