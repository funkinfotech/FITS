<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;


class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Plain-text title for the browser tab. The on-screen heading is the
     * inline-editable widget returned by getHeading().
     */
    public function getTitle(): string
    {
        return $this->record->subject;
    }

    public function getHeading(): Htmlable
    {
        return new HtmlString(view('filament.resources.ticket-resource.pages.subject-heading', [
            'subject' => $this->record->subject,
        ])->render());
    }

    public function getBreadcrumb(): string
    {
        return 'Ticket #' . $this->record->ticket_number;
    }

    /**
     * The "solid" ticket facts, shown under the subject.
     */
    public function getSubheading(): string
    {
        return sprintf(
            '#%s · %s · opened %s',
            $this->record->ticket_number,
            ucfirst((string) $this->record->source),
            $this->record->created_at?->inDisplayTz()->format('M j, Y \a\t g:i A') ?? '—',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * No footer buttons — every field commits itself (see autosave()).
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Persist the current form state without redirecting or firing Filament's
     * default "Saved" toast. Called from each field's afterStateUpdated hook and
     * from the inline subject editor when they lose focus.
     */
    public function autosave(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        // Reload so the read-only "link" placeholders reflect fresh relations
        // (e.g. the company/contact name) after the write.
        $this->record->refresh();

        // Tells the inline attribute editors to collapse back to their links.
        $this->dispatch('ticket-autosaved');

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
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
