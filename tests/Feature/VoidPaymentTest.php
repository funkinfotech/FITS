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

    public function test_voiding_a_payment_on_a_voided_invoice_does_not_resurrect_it(): void
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

        // The invoice was separately written off/voided after the payment was recorded.
        $invoice->update(['status' => InvoiceStatus::Void]);

        Livewire::test(ListPayments::class)
            ->callTableAction('void', $payment, data: ['void_reason' => 'Correcting records']);

        $payment->refresh();
        $this->assertTrue($payment->is_voided);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);

        Carbon::setTestNow();
    }
}
