<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Form;
use Filament\Forms;
use Livewire\Attributes\On;


class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {

        return $this->getResource()::getUrl('index');
    }

    #[On('ticket-updated')]
    public function refreshTicket(): void
    {
        $this->record->refresh();
        $this->refreshFormData(['status']);
    }
}
