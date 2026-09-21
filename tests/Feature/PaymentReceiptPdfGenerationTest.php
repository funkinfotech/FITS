<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\BusinessProfile;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentReceiptPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_writes_a_pdf_to_the_local_disk_and_stamps_the_payment(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '200.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Check->value,
            'reference' => 'Check #4821',
        ]);

        $path = PaymentReceiptPdfGenerator::generate($payment->fresh());

        Storage::disk('local')->assertExists($path);

        $payment->refresh();
        $this->assertSame($path, $payment->pdf_path);
        $this->assertNotNull($payment->pdf_generated_at);
    }

    public function test_logo_uses_the_current_business_profile_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/current.png', 'current-logo-bytes');
        BusinessProfile::current()->update(['logo_path' => 'branding/current.png']);

        $dataUri = PaymentReceiptPdfGenerator::logoDataUri();

        $this->assertStringContainsString(base64_encode('current-logo-bytes'), $dataUri);
    }

    public function test_generate_ignores_the_invoices_frozen_logo_snapshot(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('branding/current.png', 'current-logo-bytes');
        BusinessProfile::current()->update(['logo_path' => 'branding/current.png']);

        $company = Company::create(['name' => 'Acme Corp']);
        // Simulates an invoice created before the logo above was uploaded —
        // its own snapshot points at a file that doesn't even exist anymore.
        $invoice = Invoice::create(['company_id' => $company->id, 'from_logo_path' => 'branding/stale-and-missing.png']);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0002',
            'year' => 2026,
            'sequence' => 2,
            'amount' => '100.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Cash->value,
        ]);

        // Generating must not error just because the invoice's own logo
        // snapshot is stale/missing — the receipt no longer looks at it.
        $path = PaymentReceiptPdfGenerator::generate($payment->fresh());

        Storage::disk('local')->assertExists($path);
    }
}
