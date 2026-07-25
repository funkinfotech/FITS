<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\Contact;
use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Actions\ViewAction;
use App\Models\User;


class TicketResource extends Resource
{

    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Tickets';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
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
             

            Select::make('assigned_to')
                ->label('Assigned To')
                ->options(fn () => User::where('is_admin', true)->pluck('name', 'id'))
                ->searchable()
                ->native(false)
                ->placeholder('Unassigned'),

            Select::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('contact_id', null))
                ->native(false)
                ->placeholder('None'),

            Select::make('contact_id')
                ->label('Contact')
                ->options(fn (Get $get): array => Contact::where('company_id', $get('company_id'))->pluck('name', 'id')->toArray())
                ->searchable()
                ->native(false)
                ->placeholder('None'),

            TextInput::make('subject')->required(),
            Textarea::make('message')->required()->rows(6),
            ]);
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
                    ->color(fn ($state) => $state ? 'success' : 'gray')
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
                    ->color(fn (string $state): string => $state === 'email' ? 'info' : 'gray')
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
            ->actions([
                ViewAction::make(),
                EditAction::make(),
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
            'edit' => Pages\EditTicket::route('/{record}/edit'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }

    public static function getModel(): string
    {
        return \App\Models\Ticket::class;
    }
}
