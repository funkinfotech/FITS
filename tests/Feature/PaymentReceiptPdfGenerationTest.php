<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
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
}
