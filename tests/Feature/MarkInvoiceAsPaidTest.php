<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarkInvoiceAsPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_invoice_as_paid_records_a_payment_and_notifies(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Ach->value,
                'reference' => 'Transfer #123',
                'notes' => 'Paid in full',
                'contact_ids' => [$contact->id],
            ]);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame(PaymentMethod::Ach, $payment->method);
        $this->assertSame('Transfer #123', $payment->reference);
        $this->assertNotNull($payment->pdf_path);
        $this->assertNotNull($payment->emailed_at);
        $this->assertNotNull($payment->admin_notified_at);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('jane@acme.test'));
        Mail::assertQueued(PaymentReceivedAdminNotification::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_leaving_contacts_unchecked_skips_the_client_email_but_still_notifies_admins(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Cash->value,
                'contact_ids' => [],
            ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($payment->emailed_at);
        $this->assertNotNull($payment->admin_notified_at);

        Mail::assertNotQueued(PaymentReceiptMail::class);
        Mail::assertQueued(PaymentReceivedAdminNotification::class, 1);
    }

    public function test_amount_must_be_at_least_a_cent(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '0',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Cash->value,
                'contact_ids' => [],
            ])
            ->assertHasTableActionErrors(['amount']);

        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());

        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '-10',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Cash->value,
                'contact_ids' => [],
            ])
            ->assertHasTableActionErrors(['amount']);

        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
    }

    public function test_marking_an_invoice_as_paid_twice_is_rejected_when_an_active_payment_already_exists(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '250.00',
            'paid_date' => '2026-09-19',
            'method' => PaymentMethod::Cash->value,
        ]);

        // The invoice is still "Sent" (a stale tab / double-submit scenario), so the action
        // remains visible even though a Payment row already exists for it — the guard inside
        // the action closure is what must stop a second Payment from being created.
        Livewire::test(ListInvoices::class)
            ->callTableAction('mark-as-paid', $invoice, data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Ach->value,
                'contact_ids' => [],
            ]);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        Mail::assertNotQueued(PaymentReceiptMail::class);
        Mail::assertNotQueued(PaymentReceivedAdminNotification::class);
    }
}
