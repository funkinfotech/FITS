<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\BusinessProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Support\InvoiceMailer;
use App\Support\InvoicePdfGenerator;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Billing';

    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Bill To')
                ->schema([
                    Select::make('company_id')
                        ->label('Company')
                        ->relationship('company', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),
                ]),

            Section::make('Invoice Details')
                ->schema([
                    Placeholder::make('invoice_number_preview')
                        ->label('Invoice #')
                        ->content('Will be assigned automatically on save')
                        ->visible(fn (string $operation): bool => $operation === 'create'),

                    TextInput::make('invoice_number')
                        ->label('Invoice #')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),

                    Select::make('status')
                        ->required()
                        ->options(collect(InvoiceStatus::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                        )
                        ->default(InvoiceStatus::Draft->value)
                        ->native(false)
                        ->disablePlaceholderSelection(),

                    DatePicker::make('issue_date')
                        ->required()
                        ->default(now())
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, string $operation) {
                            if ($operation !== 'create') {
                                return;
                            }

                            $issueDate = $get('issue_date');

                            if (! $issueDate) {
                                return;
                            }

                            $set('due_date', Carbon::parse($issueDate)
                                ->addDays(BusinessProfile::current()->default_net_days)
                                ->toDateString());
                        }),

                    DatePicker::make('due_date')
                        ->required()
                        ->default(fn () => now()->addDays(BusinessProfile::current()->default_net_days)),
                ])
                ->columns(2),

            Section::make('Line Items')
                ->schema([
                    Repeater::make('lineItems')
                        ->label('')
                        ->relationship('lineItems')
                        ->reorderable()
                        ->orderColumn('sort')
                        ->schema([
                            TextInput::make('description')
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set(
                                    'amount',
                                    round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2)
                                )),

                            TextInput::make('unit_price')
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set(
                                    'amount',
                                    round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2)
                                )),

                            TextInput::make('amount')
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->disabled()
                                ->dehydrated(true),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->disabled(fn (string $operation, ?Invoice $record): bool => $operation === 'edit' && $record && ! $record->is_editable),

                    Placeholder::make('total_display')
                        ->label('Total')
                        ->content(fn (Get $get): string => '$' . number_format(
                            collect($get('lineItems') ?? [])->sum(fn ($row) => (float) ($row['amount'] ?? 0)),
                            2
                        )),
                ]),

            Section::make('Terms & Notes')
                ->schema([
                    Textarea::make('terms')
                        ->default(fn () => BusinessProfile::current()->default_terms_text)
                        ->rows(3)
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Bill To')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                BadgeColumn::make('status')
                    ->sortable()
                    ->color(fn ($state) => $state?->filamentColor())
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),

                TextColumn::make('recurringCharge.description')
                    ->label('Recurring Charge')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total')
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->since()
                    ->placeholder('Not sent')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                    ),

                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->where('due_date', '<', today())
                        ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value])
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Invoice $record): string => route('invoices.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record): bool => filled($record->pdf_path)),

                Action::make('regenerate-pdf')
                    ->label('Regenerate PDF')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => $record->is_editable)
                    ->action(fn (Invoice $record) => InvoicePdfGenerator::generate($record)),

                Action::make('email')
                    ->label('Email Invoice')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        CheckboxList::make('contact_ids')
                            ->label('Send to')
                            ->options(fn (Invoice $record): array => $record->company?->contacts
                                ->mapWithKeys(fn ($contact) => [
                                    $contact->id => $contact->name . ($contact->email ? " ({$contact->email})" : ' — no email on file'),
                                ])
                                ->all() ?? [])
                            ->default(fn (Invoice $record): array => $record->company?->contacts
                                ->filter(fn ($contact) => filled($contact->email))
                                ->pluck('id')
                                ->all() ?? [])
                            ->required(),

                        Textarea::make('custom_message')
                            ->label('Optional message')
                            ->rows(3),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $contacts = Contact::with('emails')->whereIn('id', $data['contact_ids'])->get();

                        InvoiceMailer::send($record, $contacts, $data['custom_message'] ?? null);

                        Notification::make()
                            ->title('Invoice emailed')
                            ->success()
                            ->send();
                    }),

                Action::make('mark-as-paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true))
                    ->action(fn (Invoice $record) => $record->update(['status' => InvoiceStatus::Paid])),

                Action::make('send-overdue-reminder')
                    ->label('Send Reminder')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Email this customer a payment reminder for this overdue invoice.')
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Overdue)
                    ->action(function (Invoice $record) {
                        $contacts = $record->company?->contacts ?? collect();

                        if ($contacts->isEmpty()) {
                            Notification::make()
                                ->title('No contacts to email')
                                ->danger()
                                ->send();

                            return;
                        }

                        InvoiceMailer::sendOverdueReminder($record, $contacts);
                        $record->forceFill(['overdue_ignored_at' => null])->saveQuietly();

                        Notification::make()
                            ->title('Reminder sent to customer')
                            ->success()
                            ->send();
                    }),

                Action::make('ignore-overdue')
                    ->label('Ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->requiresConfirmation()
                    ->modalDescription("Dismiss this overdue invoice without emailing the customer. You won't be notified about it again.")
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Overdue && ! $record->overdue_ignored_at)
                    ->action(function (Invoice $record) {
                        $record->forceFill(['overdue_ignored_at' => now()])->saveQuietly();

                        Notification::make()
                            ->title('Overdue reminder dismissed')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
