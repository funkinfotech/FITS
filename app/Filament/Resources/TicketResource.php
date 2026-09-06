<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Models\Ticket;
use App\Models\Contact;
use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Grid;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Closure;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\EditAction;
use App\Models\User;


class TicketResource extends Resource
{

    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Tickets';
    }

    protected static function applyContact(Set $set, ?Contact $contact): void
    {
        $set('name', $contact?->name);

        $emails = $contact?->emails ?? collect();
        $primary = $emails->firstWhere('is_primary', true) ?? $emails->first();

        $set('email', $primary?->email);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Group::make(static::createFormSchema())
                ->visibleOn('create')
                ->columnSpanFull(),

            Group::make(static::workFormSchema())
                ->visibleOn('edit')
                ->columnSpanFull(),
        ]);
    }

    /**
     * Fields shown when an agent opens an existing ticket: just the editable
     * attribute row. Subject is edited in the page heading; the message and all
     * replies live in the Conversation panel below.
     */
    protected static function workFormSchema(): array
    {
        return [
            // The subject is edited inline in the page heading; keep it in the
            // form so validation and the autosave in EditTicket cover it.
            Hidden::make('subject')->required(),

            // Editable attributes: each renders as a link showing the current
            // value and swaps to a searchable dropdown when clicked.
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->schema([
                    static::inlineAttribute(
                        'status',
                        'Status',
                        fn (Ticket $record): string => $record->status?->value ?? '—',
                        fn (Select $select): Select => $select
                            ->required()
                            ->options(collect(TicketStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->value]))
                            ->selectablePlaceholder(false),
                    ),

                    static::inlineAttribute(
                        'priority',
                        'Priority',
                        fn (Ticket $record): string => $record->priority?->value ?? '—',
                        fn (Select $select): Select => $select
                            ->required()
                            ->options(collect(TicketPriority::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->value]))
                            ->selectablePlaceholder(false),
                    ),

                    static::inlineAttribute(
                        'assigned_to',
                        'Assigned to',
                        fn (Ticket $record): string => $record->assignedTo?->name ?? 'Unassigned',
                        fn (Select $select): Select => $select
                            ->options(fn () => User::where('is_admin', true)->pluck('name', 'id'))
                            ->placeholder('Unassigned'),
                    ),

                    static::inlineAttribute(
                        'company_id',
                        'Company',
                        fn (Ticket $record): string => $record->company?->name ?? 'None',
                        fn (Select $select): Select => $select
                            ->relationship('company', 'name')
                            ->preload()
                            ->placeholder('None')
                            ->afterStateUpdated(fn (Set $set) => $set('contact_id', null)),
                    ),

                    static::inlineAttribute(
                        'contact_id',
                        'Contact',
                        fn (Ticket $record): string => $record->contact?->name ?? 'None',
                        fn (Select $select): Select => $select
                            ->options(fn (Get $get): array => Contact::where('company_id', $get('company_id'))
                                ->pluck('name', 'id')
                                ->toArray())
                            ->placeholder('None'),
                    ),
                ]),

            // The customer's original message is rendered as the last (oldest)
            // entry in the Conversation panel, not here — see
            // TicketCommentsRelationManager.
        ];
    }

    /**
     * One editable ticket attribute. Renders the current value as a link; a
     * click swaps it for a searchable {@see Select} (opened, ready to filter)
     * that autosaves on change and collapses back to the link afterwards.
     *
     * @param  Closure(Ticket): string  $displayUsing
     * @param  Closure(Select): Select  $configureSelect
     */
    protected static function inlineAttribute(string $name, string $label, Closure $displayUsing, Closure $configureSelect): Group
    {
        $event = 'ticket-reveal-' . str_replace('_', '-', $name);

        $select = $configureSelect(
            Select::make($name)
                ->hiddenLabel()
                ->native(false)
                ->searchable()
                ->live()
                ->extraFieldWrapperAttributes(['x-show' => 'editing'])
                ->extraAlpineAttributes(['x-on:' . $event . '.window' => 'select?.showDropdown?.()'])
        );

        $select->afterStateUpdated(fn ($livewire) => static::autosave($livewire));

        return Group::make([
            Placeholder::make($name . '_link')
                ->label($label)
                ->hiddenLabel()
                ->content(fn (Ticket $record): HtmlString => new HtmlString(
                    '<span class="ticket-attr__label">' . e($label) . '</span>'
                    . '<span class="ticket-attr__value">' . e($displayUsing($record)) . '</span>'
                ))
                ->extraAttributes([
                    'x-show' => '! editing',
                    'x-on:click' => 'reveal()',
                    'x-on:keydown.enter' => 'reveal()',
                    'role' => 'button',
                    'tabindex' => '0',
                    'class' => 'ticket-attr',
                ]),

            $select,
        ])->extraAttributes([
            'x-data' => '{ editing: false, reveal() { this.editing = true; this.$nextTick(() => this.$dispatch(' . Js::from($event) . ')) } }',
            'x-on:ticket-autosaved.window' => 'editing = false',
            'x-on:click.outside' => 'editing = false',
            'x-on:keydown.escape' => 'editing = false',
            'class' => 'ticket-attr__group',
        ]);
    }

    /**
     * Commit the current form state straight away. Used by every field on the
     * edit screen so there is no "Save changes" step.
     */
    protected static function autosave($livewire): void
    {
        if (method_exists($livewire, 'autosave')) {
            $livewire->autosave();
        }
    }

    protected static function createFormSchema(): array
    {
        return [

            Select::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    $defaultContact = $state
                        ? Contact::with('emails')->where('company_id', $state)->oldest()->first()
                        : null;

                    $set('contact_id', $defaultContact?->id);
                    self::applyContact($set, $defaultContact);
                })
                ->native(false)
                ->placeholder('None'),

            Select::make('contact_id')
                ->label('Contact')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->options(fn (Get $get): array => Contact::where('company_id', $get('company_id'))->pluck('name', 'id')->toArray())
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    self::applyContact($set, $state ? Contact::with('emails')->find($state) : null);
                })
                ->native(false)
                ->placeholder('None')
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data, Get $get): int {
                    $contact = Contact::create([
                        'company_id' => $get('company_id'),
                        'name' => $data['name'],
                        'phone' => $data['phone'] ?? null,
                    ]);

                    $contact->emails()->create([
                        'email' => $data['email'],
                        'is_primary' => true,
                    ]);

                    return $contact->id;
                }),

            Hidden::make('name'),
            Hidden::make('email'),

            Select::make('priority')
                ->required()
                ->options(collect(TicketPriority::cases())
                    ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                )
                ->default(TicketPriority::Medium->value)
                ->native(false)
                ->disablePlaceholderSelection(),

            Select::make('status')
                ->required()
                ->options(collect(TicketStatus::cases())
                    ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                )
                ->default(TicketStatus::Open->value)
                ->native(false)
                ->disablePlaceholderSelection(),


            TextInput::make('subject')->required(),

            Select::make('assigned_to')
                ->label('Assigned To')
                ->options(fn () => User::where('is_admin', true)->pluck('name', 'id'))
                ->searchable()
                ->native(false)
                ->placeholder('Unassigned'),

            Textarea::make('message')->required()->rows(6),

            \Filament\Forms\Components\FileUpload::make('attachments')
                ->label('Attachments')
                ->multiple()
                ->storeFiles(false)
                ->openable()
                ->reorderable(false)
                ->maxFiles(config('attachments.max_files'))
                ->maxSize(config('attachments.max_size_kb'))
                ->acceptedFileTypes(collect(config('attachments.allowed'))
                    ->flatten()
                    ->reject(fn (string $mime): bool => $mime === 'application/octet-stream')
                    ->unique()->values()->all())
                ->helperText('Images, PDF, Office documents, text/logs or .zip — sanitised on upload.')
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
                
                BadgeColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->color(fn ($state) => $state?->filamentColor())
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->color(fn ($state) => $state?->filamentColor())
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),

                         
                TextColumn::make('subject')
                    ->label('Subject')
                    ->sortable()
                    ->searchable()
                    ->limit(30),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'danger')
                    ->default('Unassigned')
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                TextColumn::make('contact.name')
                    ->label('Contact')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                BadgeColumn::make('source')
                    ->label('Source')
                    ->sortable()
                    ->color(fn (string $state): string => $state === 'email' ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(TicketStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                    ),

                SelectFilter::make('priority')
                    ->options(collect(TicketPriority::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                    ),

                SelectFilter::make('assigned_to')
                    ->label('Assigned To')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('source')
                    ->options([
                        'portal' => 'Portal',
                        'email' => 'Email',
                    ]),

                Filter::make('my_tickets')
                    ->label('My Tickets')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('assigned_to', auth()->id())),

                Filter::make('unassigned')
                    ->label('Unassigned')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('assigned_to')),
            ])
            ->recordUrl(fn (Ticket $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([
                EditAction::make()
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TicketCommentsRelationManager::class,
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Support';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}'),
        ];
    }

    public static function getModel(): string
    {
        return \App\Models\Ticket::class;
    }
}
