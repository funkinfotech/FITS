<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Support\InvoiceMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_send_queues_one_email_per_contact_with_an_email_and_attaches_the_pdf(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);

        $withEmail = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $withEmail->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $withoutEmail = Contact::create(['company_id' => $company->id, 'name' => 'No Email']);

        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);

        InvoiceMailer::send($invoice, [$withEmail, $withoutEmail]);

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail) use ($withEmail) {
            return $mail->hasTo($withEmail->email) && count($mail->attachments()) === 1;
        });

        Mail::assertQueued(InvoiceMail::class, 1);

        $invoice->refresh();
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
    }

    public function test_resending_an_already_sent_invoice_regenerates_the_pdf(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);

        Carbon::setTestNow('2026-09-01 10:00:00');
        InvoiceMailer::send($invoice, [$contact]);
        $firstGeneratedAt = $invoice->fresh()->pdf_generated_at;

        // The invoice's own line items haven't changed, but the PDF's "previous
        // balance" summary is computed live from the company's other open
        // invoices — so a resend must always re-render it, not reuse the old file.
        Carbon::setTestNow('2026-09-02 10:00:00');
        InvoiceMailer::send($invoice->fresh(), [$contact]);

        $this->assertNotEquals(
            $firstGeneratedAt->toDateTimeString(),
            $invoice->fresh()->pdf_generated_at->toDateTimeString()
        );
    }
}
