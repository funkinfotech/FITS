<?php

namespace Tests\Feature;

use App\Filament\Exports\PaymentExporter;
use Tests\TestCase;

class PaymentExporterTest extends TestCase
{
    public function test_columns_match_the_expected_ledger_shape(): void
    {
        $columns = collect(PaymentExporter::getColumns())->map(fn ($column) => $column->getName())->all();

        $this->assertSame([
            'paid_date',
            'receipt_number',
            'invoice.invoice_number',
            'invoice.company.name',
            'amount',
            'method',
            'reference',
            'notes',
        ], $columns);
    }
}
