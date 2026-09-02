<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Support\InvoicePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_writes_a_pdf_to_the_local_disk_and_stamps_the_invoice(): void
    {
        Storage::fake('local');

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);
        $invoice->lineItems()->create(['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100]);

        $path = InvoicePdfGenerator::generate($invoice->fresh());

        Storage::disk('local')->assertExists($path);

        $invoice->refresh();
        $this->assertSame($path, $invoice->pdf_path);
        $this->assertNotNull($invoice->pdf_generated_at);
    }
}
