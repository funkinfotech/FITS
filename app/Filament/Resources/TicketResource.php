<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;


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
                ->options(
                    collect(TicketPriority::cases())->mapWithKeys(fn ($case) => [
                        $case->value => $case->value,
                    ])
                )
                ->default(TicketPriority::Medium->value)
                ->native(false)
                ->disablePlaceholderSelection(),

            Select::make('status')
                ->required()
                ->options(
                    collect(TicketStatus::cases())->mapWithKeys(fn ($case) => [
                        $case->value => $case->value,
                    ])
                )
                ->default(TicketStatus::Open->value)
                ->native(false)
                ->disablePlaceholderSelection(),
             

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
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                BadgeColumn::make('priority')
                    ->label('Priority')
                    ->default('Medium')
                    ->colors([
                        'Low' => 'info',
                        'Medium' => 'warning',
                        'High' => 'danger',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->default('Open')
                    ->colors([
                        'Open' => 'primary',
                        'In Progress' => 'warning',
                        'Closed' => 'success',
                    ])
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\TicketStatus ? $state->value : ucwords($state)),          
                TextColumn::make('subject')->limit(30),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([
                //
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
            //
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