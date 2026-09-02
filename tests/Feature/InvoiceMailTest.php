<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Support\InvoiceMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

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
}
