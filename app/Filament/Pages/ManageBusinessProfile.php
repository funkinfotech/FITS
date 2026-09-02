<?php

namespace App\Filament\Pages;

use App\Models\BusinessProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageBusinessProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Company Profile';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.manage-business-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(BusinessProfile::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public')
                    ->imagePreviewHeight('120')
                    ->columnSpanFull(),

                TextInput::make('business_name')
                    ->label('Business Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('tax_id')
                    ->label('Tax ID')
                    ->maxLength(255),

                Textarea::make('address')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('bank_details')
                    ->label('Bank / Payment Instructions')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('default_net_days')
                    ->label('Default Payment Terms (Net Days)')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Textarea::make('default_terms_text')
                    ->label('Default Invoice Terms')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        BusinessProfile::current()->update($this->form->getState());

        Notification::make()
            ->title('Company profile saved')
            ->success()
            ->send();
    }
}
