<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark-as-paid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true))
                ->form(InvoiceResource::markAsPaidFormSchema())
                ->action(fn (Invoice $record, array $data) => InvoiceResource::handleMarkAsPaid($record, $data)),

            Actions\EditAction::make(),
        ];
    }
}
