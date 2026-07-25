<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Support\PortalAccountProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;

    protected ?bool $createPortalUser = null;
    protected ?string $portalPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->createPortalUser = (bool) ($data['create_portal_user'] ?? false);
        $this->portalPassword = $data['portal_password'] ?? null;

        unset($data['create_portal_user'], $data['portal_password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->createPortalUser || ! $this->portalPassword) {
            return;
        }

        PortalAccountProvisioner::createForContact($this->record, $this->portalPassword);

        Notification::make()
            ->title('Portal login created')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
