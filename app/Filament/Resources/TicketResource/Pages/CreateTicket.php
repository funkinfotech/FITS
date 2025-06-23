<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;


class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['user_id'] = Auth::id(); // Assign currently logged-in user
        return static::getModel()::create($data);
    }

    protected function getRedirectUrl(): string
    {
        // Redirect after successful creation
        return static::getResource()::getUrl('index');
    }
}
