<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentExporterEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for "CSV export will fail at runtime — the exports table doesn't exist":
     * this exercises the real ExportAction pipeline (query -> Export row -> queued export job
     * chain) rather than only asserting on getColumns(), so it would have caught the missing
     * filament/actions migrations (exports/imports/failed_import_rows) at runtime.
     */
    public function test_triggering_the_header_export_action_completes_without_error_and_writes_a_csv(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '150.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Check->value,
        ]);

        Livewire::test(ListPayments::class)
            ->callTableAction('export');

        $export = Export::query()->latest('id')->first();

        $this->assertNotNull(
            $export,
            'Expected the export action to create an Export row (this is what fails with '
            . '"no such table: exports" if the filament/actions migrations are not published).'
        );
        $this->assertSame(PaymentExporter::class, $export->exporter);
        $this->assertSame('local', $export->file_disk);
        $this->assertNotNull(
            $export->completed_at,
            'Expected the export job chain to run inline (QUEUE_CONNECTION=sync in tests) and mark the export completed.'
        );
        $this->assertSame(1, $export->successful_rows);
        $this->assertSame(0, $export->getFailedRowsCount());

        // The exported CSV page should actually exist on the disk the export recorded to,
        // proving the whole pipeline (including the "exports" table row) ran end to end.
        Storage::disk($export->file_disk)->assertExists(
            $export->getFileDirectory() . '/0000000000000001.csv'
        );
    }
}
