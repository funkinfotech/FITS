<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_invoice_with_line_items_through_the_form_persists_them(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);

        $component = Livewire::test(CreateInvoice::class)
            ->set('data.company_id', $company->id);

        $itemKey = array_key_first($component->get('data.lineItems'));

        $component
            ->set("data.lineItems.{$itemKey}.description", 'Consulting')
            ->set("data.lineItems.{$itemKey}.quantity", 2)
            ->set("data.lineItems.{$itemKey}.unit_price", 100)
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('company_id', $company->id)->firstOrFail();

        $this->assertNotNull($invoice->invoice_number);
        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('Consulting', $invoice->lineItems->first()->description);
        $this->assertSame('200.00', $invoice->lineItems->first()->amount);
        $this->assertSame('200.00', $invoice->total);
        $this->assertNotNull($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path);
    }
}
