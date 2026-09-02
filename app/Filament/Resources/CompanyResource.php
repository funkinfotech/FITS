<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers\RecurringChargesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationLabel = 'Companies';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('phone')
                ->tel()
                ->maxLength(255),

            Textarea::make('address')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('notes')
                ->rows(4)
                ->columnSpanFull(),

            Repeater::make('contacts')
                ->relationship()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),

                    Repeater::make('emails')
                        ->relationship()
                        ->schema([
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),

                            Checkbox::make('is_primary')
                                ->label('Primary'),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->minItems(1)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone'),

                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Portal Users')
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->sortable(),

                TextColumn::make('balance_owed')
                    ->label('Balance Owed')
                    ->money('usd')
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            RecurringChargesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
