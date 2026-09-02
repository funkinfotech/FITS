<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecurringChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringCharges';

    protected static ?string $title = 'Recurring Charges';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('description')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->prefix('$')
                ->required(),

            Forms\Components\Select::make('billing_day')
                ->label('Bills On')
                ->options(collect(range(1, 28))->mapWithKeys(fn ($day) => [$day => "Day {$day}"]))
                ->required()
                ->default(1)
                ->native(false),

            Forms\Components\DatePicker::make('starts_on')
                ->required()
                ->default(now()->toDateString()),

            Forms\Components\DatePicker::make('ends_on')
                ->label('Ends On')
                ->helperText('Leave blank for an ongoing charge.'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('usd')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_day')
                    ->label('Bills On')
                    ->formatStateUsing(fn ($state) => "Day {$state}")
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_invoiced_on')
                    ->label('Last Invoiced')
                    ->date()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_on')
                    ->label('Ends On')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
