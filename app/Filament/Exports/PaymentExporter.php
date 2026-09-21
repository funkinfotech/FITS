<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class PaymentExporter extends Exporter
{
    protected static ?string $model = Payment::class;

    public function getFileDisk(): string
    {
        return 'local';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('invoice.company');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('paid_date')
                ->label('Date'),

            ExportColumn::make('receipt_number')
                ->label('Receipt #'),

            ExportColumn::make('invoice.invoice_number')
                ->label('Invoice #'),

            ExportColumn::make('invoice.company.name')
                ->label('Company'),

            ExportColumn::make('amount')
                ->label('Amount'),

            ExportColumn::make('method')
                ->label('Method')
                ->formatStateUsing(fn ($state) => $state?->value),

            ExportColumn::make('reference')
                ->label('Reference'),

            ExportColumn::make('notes')
                ->label('Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
