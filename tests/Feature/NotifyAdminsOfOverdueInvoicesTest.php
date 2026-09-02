<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceOverdueAdminNotification;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyAdminsOfOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeOverdueInvoice(): Invoice
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);
        $invoice->update(['status' => InvoiceStatus::Overdue]);

        return $invoice;
    }

    public function test_it_emails_every_admin_about_an_overdue_invoice_never_notified_before(): void
    {
        Mail::fake();

        $admin1 = User::factory()->create(['is_admin' => true, 'email' => 'admin1@funkit.test']);
        $admin2 = User::factory()->create(['is_admin' => true, 'email' => 'admin2@funkit.test']);
        User::factory()->create(['is_admin' => false, 'email' => 'notadmin@funkit.test']);

        $invoice = $this->makeOverdueInvoice();

        $this->artisan('invoices:notify-admins-overdue')->assertSuccessful();

        Mail::assertQueued(InvoiceOverdueAdminNotification::class, function (InvoiceOverdueAdminNotification $mail) use ($admin1) {
            return $mail->hasTo($admin1->email);
        });
        Mail::assertQueued(InvoiceOverdueAdminNotification::class, function (InvoiceOverdueAdminNotification $mail) use ($admin2) {
            return $mail->hasTo($admin2->email);
        });
        Mail::assertQueued(InvoiceOverdueAdminNotification::class, 2);

        $this->assertNotNull($invoice->fresh()->overdue_admin_notified_at);
    }

    public function test_it_does_not_re_notify_within_the_throttle_window(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-08-25 12:00:00');

        User::factory()->create(['is_admin' => true]);

        $invoice = $this->makeOverdueInvoice();
        $invoice->forceFill(['overdue_admin_notified_at' => now()->subDay()])->save();

        $this->artisan('invoices:notify-admins-overdue');

        Mail::assertNothingQueued();
    }

    public function test_it_re_notifies_once_the_throttle_window_has_passed(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-08-25 12:00:00');

        User::factory()->create(['is_admin' => true]);

        $invoice = $this->makeOverdueInvoice();
        $invoice->forceFill(['overdue_admin_notified_at' => now()->subDays(4)])->save();

        $this->artisan('invoices:notify-admins-overdue');

        Mail::assertQueued(InvoiceOverdueAdminNotification::class, 1);
    }

    public function test_it_skips_invoices_the_admin_has_ignored(): void
    {
        Mail::fake();

        User::factory()->create(['is_admin' => true]);

        $invoice = $this->makeOverdueInvoice();
        $invoice->forceFill(['overdue_ignored_at' => now()])->save();

        $this->artisan('invoices:notify-admins-overdue');

        Mail::assertNothingQueued();
    }

    public function test_non_overdue_invoices_are_ignored(): void
    {
        Mail::fake();

        User::factory()->create(['is_admin' => true]);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->artisan('invoices:notify-admins-overdue');

        Mail::assertNothingQueued();
    }
}
