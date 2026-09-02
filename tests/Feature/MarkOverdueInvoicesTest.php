<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeInvoice(InvoiceStatus $status, string $dueDate): Invoice
    {
        $company = Company::create(['name' => 'Acme Corp']);

        $invoice = Invoice::create(['company_id' => $company->id, 'due_date' => $dueDate]);
        $invoice->update(['status' => $status]);

        return $invoice;
    }

    public function test_sent_invoice_past_due_date_becomes_overdue(): void
    {
        Carbon::setTestNow('2026-08-25');

        $invoice = $this->makeInvoice(InvoiceStatus::Sent, '2026-08-01');

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_sent_invoice_not_yet_due_stays_sent(): void
    {
        Carbon::setTestNow('2026-08-25');

        $invoice = $this->makeInvoice(InvoiceStatus::Sent, '2026-09-01');

        $this->artisan('invoices:mark-overdue');

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }

    public function test_non_sent_statuses_are_left_untouched_even_when_past_due(): void
    {
        Carbon::setTestNow('2026-08-25');

        $paid = $this->makeInvoice(InvoiceStatus::Paid, '2026-08-01');
        $void = $this->makeInvoice(InvoiceStatus::Void, '2026-08-01');
        $draft = $this->makeInvoice(InvoiceStatus::Draft, '2026-08-01');
        $rolledOver = $this->makeInvoice(InvoiceStatus::RolledOver, '2026-08-01');

        $this->artisan('invoices:mark-overdue');

        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::Void, $void->fresh()->status);
        $this->assertSame(InvoiceStatus::Draft, $draft->fresh()->status);
        $this->assertSame(InvoiceStatus::RolledOver, $rolledOver->fresh()->status);
    }
}
