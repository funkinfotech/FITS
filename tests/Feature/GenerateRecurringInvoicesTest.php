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

    public function test_an_older_unpaid_invoice_is_left_untouched_and_surfaced_as_previous_balance(): void
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

        // The July invoice is a permanent, independent record: this cycle
        // never mutates it, its status, or its total.
        $julyInvoice->refresh();
        $this->assertSame(InvoiceStatus::Overdue, $julyInvoice->status);
        $this->assertSame('150.00', $julyInvoice->total);

        $augustInvoice = Invoice::where('id', '!=', $julyInvoice->id)->sole();
        $this->assertSame(1, $augustInvoice->lineItems()->count());
        $this->assertSame('Managed Services', $augustInvoice->lineItems()->first()->description);
        $this->assertSame('150.00', $augustInvoice->total);

        // The old balance is surfaced as a read-only summary, not folded in.
        $this->assertSame('150.00', $augustInvoice->previous_balance);
        $this->assertSame('300.00', $augustInvoice->total_balance_due);
    }

    public function test_previous_balance_accumulates_across_multiple_missed_cycles(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->makeCompanyWithContact();

        RecurringCharge::create([
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

        // Three separate $150 invoices exist untouched; the newest one's own
        // total is still just this cycle's charge, with the other two
        // reflected as previous balance (150 + 150 = 300).
        $this->assertSame(3, Invoice::count());
        $this->assertSame(1, $latest->lineItems()->count());
        $this->assertSame('150.00', $latest->total);
        $this->assertSame('300.00', $latest->previous_balance);
        $this->assertSame('450.00', $latest->total_balance_due);
    }

    public function test_paid_prior_invoice_is_excluded_from_previous_balance(): void
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
        $this->assertSame('0.00', $augustInvoice->previous_balance);
    }
}
