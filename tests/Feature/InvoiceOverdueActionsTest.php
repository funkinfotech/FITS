<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Mail\InvoiceOverdueReminderMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceOverdueActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOverdueInvoiceWithContact(): Invoice
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);
        $invoice->update(['status' => InvoiceStatus::Overdue]);

        return $invoice;
    }

    public function test_send_reminder_action_emails_the_customer_and_clears_ignored_state(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $invoice = $this->makeOverdueInvoiceWithContact();
        $invoice->forceFill(['overdue_ignored_at' => now()])->save();

        Livewire::test(ListInvoices::class)
            ->callTableAction('send-overdue-reminder', $invoice);

        Mail::assertQueued(InvoiceOverdueReminderMail::class, 1);

        $invoice->refresh();
        $this->assertNotNull($invoice->overdue_reminder_sent_at);
        $this->assertNull($invoice->overdue_ignored_at);
    }

    public function test_ignore_action_dismisses_without_emailing_the_customer(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $invoice = $this->makeOverdueInvoiceWithContact();

        Livewire::test(ListInvoices::class)
            ->callTableAction('ignore-overdue', $invoice);

        Mail::assertNothingQueued();

        $invoice->refresh();
        $this->assertNotNull($invoice->overdue_ignored_at);
        $this->assertNull($invoice->overdue_reminder_sent_at);
    }

    public function test_actions_are_not_visible_on_non_overdue_invoices(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        Livewire::test(ListInvoices::class)
            ->assertTableActionHidden('send-overdue-reminder', $invoice)
            ->assertTableActionHidden('ignore-overdue', $invoice);
    }
}
