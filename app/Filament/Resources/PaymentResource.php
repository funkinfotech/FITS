<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Company;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Billing';

    public static function getNavigationLabel(): string
    {
        return 'Payments';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('receipt_number')->label('Receipt #'),
            TextEntry::make('invoice.invoice_number')->label('Invoice #'),
            TextEntry::make('invoice.company.name')->label('Company')->placeholder('—'),
            TextEntry::make('amount')->money('usd'),
            TextEntry::make('paid_date')->date(),
            TextEntry::make('method')->formatStateUsing(fn ($state) => $state?->value),
            TextEntry::make('reference')->placeholder('—'),
            TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            TextEntry::make('voided_at')
                ->label('Voided At')
                ->dateTime()
                ->visible(fn (Payment $record): bool => $record->is_voided),
            TextEntry::make('void_reason')
                ->label('Void Reason')
                ->placeholder('—')
                ->visible(fn (Payment $record): bool => $record->is_voided),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                TextColumn::make('amount')
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('paid_date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('method')
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),

                TextColumn::make('reference')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_voided')
                    ->label('Voided')
                    ->boolean(),
            ])
            ->defaultSort('paid_date', 'desc')
            ->filters([
                SelectFilter::make('method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])),

                Filter::make('company')
                    ->form([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn () => Company::pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['company_id'] ?? null,
                            fn (Builder $query, $companyId) => $query->whereHas(
                                'invoice',
                                fn (Builder $query) => $query->where('company_id', $companyId)
                            )
                        );
                    }),

                Filter::make('paid_date')
                    ->form([
                        DatePicker::make('paid_from')->label('Paid From'),
                        DatePicker::make('paid_until')->label('Paid Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['paid_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_date', '>=', $date))
                            ->when($data['paid_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_date', '<=', $date));
                    }),

                Filter::make('hide_voided')
                    ->label('Hide voided')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query) => $query->whereNull('voided_at')),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(PaymentExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PaymentExporter::class),
                ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Payment $record): string => route('payments.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => filled($record->pdf_path)),

                Action::make('void')
                    ->label('Void Payment')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => ! $record->is_voided)
                    ->form([
                        TextInput::make('void_reason')
                            ->label('Reason (optional)'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->forceFill([
                                'voided_at' => now(),
                                'void_reason' => $data['void_reason'] ?? null,
                            ])->saveQuietly();

                            $invoice = $record->invoice;

                            if ($invoice->status === InvoiceStatus::Paid) {
                                $invoice->update([
                                    'status' => $invoice->due_date->isPast()
                                        ? InvoiceStatus::Overdue
                                        : InvoiceStatus::Sent,
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('Payment voided')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
