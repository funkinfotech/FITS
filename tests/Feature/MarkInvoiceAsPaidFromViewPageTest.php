<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Mail\PaymentReceivedAdminNotification;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarkInvoiceAsPaidFromViewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_invoice_as_paid_from_the_view_page_records_a_payment(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('mark-as-paid', data: [
                'amount' => '250.00',
                'paid_date' => '2026-09-20',
                'method' => PaymentMethod::Ach->value,
                'contact_ids' => [],
            ]);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame(PaymentMethod::Ach, $payment->method);

        Mail::assertQueued(PaymentReceivedAdminNotification::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_the_button_is_hidden_once_the_invoice_is_already_paid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionHidden('mark-as-paid');
    }
}
