<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptMailTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(): Payment
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 200]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '200.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Ach->value,
        ]);
    }

    public function test_send_receipt_queues_one_email_per_contact_with_an_email_and_attaches_the_pdf(): void
    {
        Storage::fake('local');
        Mail::fake();

        $payment = $this->makePayment();

        $withEmail = Contact::create(['company_id' => $payment->invoice->company_id, 'name' => 'Jane Doe']);
        $withEmail->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $withoutEmail = Contact::create(['company_id' => $payment->invoice->company_id, 'name' => 'No Email']);

        PaymentMailer::sendReceipt($payment, [$withEmail, $withoutEmail]);

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($withEmail) {
            return $mail->hasTo($withEmail->email) && count($mail->attachments()) === 1;
        });
        Mail::assertQueued(PaymentReceiptMail::class, 1);

        $payment->refresh();
        $this->assertNotNull($payment->emailed_at);
    }

    public function test_notify_admins_queues_one_email_per_admin_user(): void
    {
        Storage::fake('local');
        Mail::fake();

        $payment = $this->makePayment();

        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);

        PaymentMailer::notifyAdmins($payment);

        Mail::assertQueued(PaymentReceivedAdminNotification::class, function (PaymentReceivedAdminNotification $mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
        Mail::assertQueued(PaymentReceivedAdminNotification::class, 1);

        $payment->refresh();
        $this->assertNotNull($payment->admin_notified_at);
    }
}
