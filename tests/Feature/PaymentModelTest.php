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
        ]);
        $voided->forceFill(['voided_at' => now()])->saveQuietly();

        $activeIds = Payment::active()->pluck('id')->all();

        $this->assertContains($active->id, $activeIds);
        $this->assertNotContains($voided->id, $activeIds);
        $this->assertFalse($active->is_voided);
        $this->assertTrue($voided->is_voided);
    }
}
