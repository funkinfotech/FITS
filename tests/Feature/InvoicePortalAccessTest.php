<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_in_the_same_company_can_view_the_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->assertTrue($user->can('view', $invoice));
    }

    public function test_coworker_at_the_same_company_can_view_the_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->assertTrue($coworker->can('view', $invoice));
    }

    public function test_user_at_a_different_company_cannot_view_the_invoice(): void
    {
        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $user = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);

        $this->assertFalse($user->can('view', $invoice));
    }

    public function test_user_with_no_company_cannot_view_any_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => null]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertFalse($user->can('view', $invoice));
    }

    public function test_an_admin_can_view_any_invoice_regardless_of_company(): void
    {
        // Regression test: this policy is auto-discovered globally by Laravel, not scoped
        // to the portal — Filament's admin InvoiceResource has no custom canView() override,
        // so it defers to this same policy for its own authorization. Admin users are staff,
        // not tied to any one client company (company_id is null), so without this bypass
        // every admin would be locked out of every invoice in the admin panel too, matching
        // the equivalent bypass already established in TicketPolicy::view().
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => null]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        $this->assertTrue($admin->can('view', $invoice));
    }

    public function test_user_can_download_their_own_invoice_pdf(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);
        $invoice->forceFill(['pdf_path' => 'invoices/2026/INV-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($invoice->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk();
    }

    public function test_invoice_pdf_download_404s_when_no_pdf_has_been_generated(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertNotFound();
    }

    public function test_user_at_a_different_company_cannot_download_the_invoice_pdf(): void
    {
        Storage::fake('local');

        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);
        $invoice->forceFill(['pdf_path' => 'invoices/2026/INV-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($invoice->pdf_path, 'fake-pdf-contents');

        $this->actingAs($outsider)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_user_can_download_the_receipt_for_a_paid_invoice(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk();
    }

    public function test_receipt_download_404s_when_the_invoice_has_no_payment(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertNotFound();
    }

    public function test_receipt_download_404s_when_the_only_payment_is_voided(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Paid]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill([
            'pdf_path' => 'payments/2026/RCPT-2026-0001.pdf',
            'voided_at' => now(),
        ])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertNotFound();
    }

    public function test_a_company_user_cannot_view_a_draft_invoice_in_their_own_company(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $this->assertFalse($user->can('view', $invoice));
    }

    public function test_an_admin_can_still_view_a_draft_invoice(): void
    {
        // Regression guard: the admin bypass in InvoicePolicy::view() must stay
        // unconditional. Filament's admin InvoiceResource defers to this policy,
        // so a status check applied to the admin branch would lock admins out of
        // draft invoices in the admin panel.
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => null]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $this->assertTrue($admin->can('view', $invoice));
    }

    public function test_a_company_user_gets_403_visiting_the_show_page_for_a_draft_invoice(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_a_company_user_gets_403_downloading_the_pdf_for_a_draft_invoice(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Draft]);
        $invoice->forceFill(['pdf_path' => 'invoices/2026/INV-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($invoice->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_a_company_user_gets_403_downloading_the_receipt_for_a_draft_invoice(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertForbidden();
    }

    public function test_a_company_user_can_still_view_a_void_invoice_in_their_own_company(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->update(['status' => InvoiceStatus::Void]);

        $this->assertTrue($user->can('view', $invoice));

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk();
    }

    public function test_user_at_a_different_company_cannot_download_the_receipt(): void
    {
        Storage::fake('local');

        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $invoice = Invoice::create(['company_id' => $companyA->id]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-21',
            'method' => PaymentMethod::Cash->value,
        ]);
        $payment->forceFill(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf'])->saveQuietly();
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($outsider)
            ->get(route('invoices.receipt', $invoice))
            ->assertForbidden();
    }
}
