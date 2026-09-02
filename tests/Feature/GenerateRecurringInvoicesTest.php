<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\RecurringCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCompanyWithContact(): Company
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        return $company;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_generates_an_invoice_when_a_charge_is_due(): void
    {
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow('2026-08-01');

        $company = $this->makeCompanyWithContact();

        $charge = RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-08-01',
        ]);

        $this->artisan('invoices:generate-recurring')->assertSuccessful();

        $this->assertSame(1, Invoice::count());

        $invoice = Invoice::first();
        $this->assertSame($charge->id, $invoice->recurring_charge_id);
        $this->assertSame(1, $invoice->lineItems()->count());
        $this->assertSame('Managed Services', $invoice->lineItems()->first()->description);
        $this->assertSame('150.00', $invoice->total);

        $charge->refresh();
        $this->assertSame('2026-08-01', $charge->last_invoiced_on->toDateString());
    }

    public function test_it_does_not_generate_twice_for_the_same_cycle(): void
    {
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow('2026-08-01');

        $company = $this->makeCompanyWithContact();

        RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-08-01',
        ]);

        $this->artisan('invoices:generate-recurring');
        $this->artisan('invoices:generate-recurring');

        $this->assertSame(1, Invoice::count());
    }

    public function test_it_does_not_generate_before_the_charge_is_due(): void
    {
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow('2026-08-01');

        $company = $this->makeCompanyWithContact();

        RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 15,
            'starts_on' => '2026-08-01',
        ]);

        $this->artisan('invoices:generate-recurring');

        $this->assertSame(0, Invoice::count());
    }

    public function test_unpaid_prior_invoice_rolls_forward_as_a_line_item_and_is_marked_rolled_over(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->makeCompanyWithContact();

        $charge = RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-06-01',
        ]);

        Carbon::setTestNow('2026-07-01');
        $this->artisan('invoices:generate-recurring');

        $julyInvoice = Invoice::sole();
        $julyInvoice->update(['status' => InvoiceStatus::Overdue]);

        Carbon::setTestNow('2026-08-01');
        $this->artisan('invoices:generate-recurring');

        $julyInvoice->refresh();
        $this->assertSame(InvoiceStatus::RolledOver, $julyInvoice->status);

        $augustInvoice = Invoice::where('id', '!=', $julyInvoice->id)->sole();
        $this->assertSame($augustInvoice->id, $julyInvoice->rolled_into_invoice_id);
        $this->assertSame(2, $augustInvoice->lineItems()->count());

        $rolloverLine = $augustInvoice->lineItems()->where('description', 'like', 'Previous balance due%')->sole();
        $this->assertStringContainsString($julyInvoice->invoice_number, $rolloverLine->description);
        $this->assertSame('150.00', $rolloverLine->amount);

        $this->assertSame('300.00', $augustInvoice->total);
    }

    public function test_multiple_unpaid_prior_invoices_all_roll_into_the_newest_invoice(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->makeCompanyWithContact();

        $charge = RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-05-01',
        ]);

        Carbon::setTestNow('2026-06-01');
        $this->artisan('invoices:generate-recurring');
        Invoice::sole()->update(['status' => InvoiceStatus::Overdue]);

        Carbon::setTestNow('2026-07-01');
        $this->artisan('invoices:generate-recurring');
        Invoice::where('status', InvoiceStatus::Sent)->sole()->update(['status' => InvoiceStatus::Overdue]);

        Carbon::setTestNow('2026-08-01');
        $this->artisan('invoices:generate-recurring');

        $latest = Invoice::orderByDesc('id')->first();

        // Each rollover consolidates the prior invoice's total into one line item,
        // so the balance compounds (150 + 150 + 150 = 450) without the line item
        // count growing per missed cycle.
        $this->assertSame(2, $latest->lineItems()->count());
        $this->assertSame('450.00', $latest->total);
        $this->assertSame(2, Invoice::where('status', InvoiceStatus::RolledOver)->count());
    }

    public function test_paid_prior_invoice_does_not_roll_forward(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->makeCompanyWithContact();

        RecurringCharge::create([
            'company_id' => $company->id,
            'description' => 'Managed Services',
            'amount' => 150,
            'billing_day' => 1,
            'starts_on' => '2026-06-01',
        ]);

        Carbon::setTestNow('2026-07-01');
        $this->artisan('invoices:generate-recurring');
        Invoice::sole()->update(['status' => InvoiceStatus::Paid]);

        Carbon::setTestNow('2026-08-01');
        $this->artisan('invoices:generate-recurring');

        $augustInvoice = Invoice::where('status', '!=', InvoiceStatus::Paid)->sole();
        $this->assertSame(1, $augustInvoice->lineItems()->count());
        $this->assertSame('150.00', $augustInvoice->total);
    }
}
