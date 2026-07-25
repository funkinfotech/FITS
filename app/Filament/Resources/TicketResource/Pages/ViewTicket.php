<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    #[On('ticket-updated')]
    public function refreshTicket(): void
    {
        $this->record->refresh();
        $this->refreshFormData(['status']);
    }
}
