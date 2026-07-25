<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Company;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationLabel = 'Contacts';
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),

                    Textarea::make('address')
                        ->rows(3),

                    Textarea::make('notes')
                        ->rows(3),
                ])
                ->createOptionModalHeading('Create Company')
                ->createOptionAction(fn ($action) => $action->modalDescription(
                    'This creates the company record. Once you save this contact, the new company will have its required first contact.'
                )),

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

            Checkbox::make('create_portal_user')
                ->label('Create a portal login for this contact')
                ->live()
                ->visibleOn('create'),

            TextInput::make('portal_password')
                ->label('Portal Password')
                ->password()
                ->revealable()
                ->required()
                ->minLength(8)
                ->visible(fn (Get $get): bool => $get('create_portal_user'))
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(query: fn (Builder $query, string $search): Builder =>
                        $query->orWhereHas('emails', fn (Builder $q) => $q->where('email', 'like', "%{$search}%"))),

                TextColumn::make('phone'),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('has_portal_login')
                    ->label('Portal Login')
                    ->boolean()
                    ->getStateUsing(fn (Contact $record): bool => $record->user !== null),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function (Contact $record) {
                        if ($record->company->contacts()->count() <= 1) {
                            Notification::make()
                                ->title('Cannot delete this contact')
                                ->body('Every company must have at least one contact.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->company->contacts()->count() <= 1) {
                                    $skipped++;
                                    continue;
                                }

                                $record->delete();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("Skipped {$skipped} contact(s)")
                                    ->body('Every company must have at least one contact.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
