<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Exceptions\RejectedAttachment;
use App\Filament\Resources\TicketResource;
use App\Support\AttachmentPipeline;
use App\Support\TicketMailer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;


class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /** @var array<int, \Illuminate\Http\UploadedFile> */
    protected array $pendingAttachments = [];

    protected function handleRecordCreation(array $data): Model
    {
        $this->pendingAttachments = array_values(array_filter((array) ($data['attachments'] ?? [])));
        unset($data['attachments']);

        $data['user_id'] = Auth::id(); // Assign currently logged-in user

        return static::getModel()::create($data);
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingAttachments as $file) {
            try {
                AttachmentPipeline::fromUpload($file, $this->record, Auth::user());
            } catch (RejectedAttachment $e) {
                Notification::make()
                    ->title('Attachment skipped')
                    ->body(AttachmentPipeline::sanitizeName($file->getClientOriginalName()) . ' — ' . $e->getMessage())
                    ->warning()
                    ->send();
            } catch (\Throwable $e) {
                report($e);
                Notification::make()->title('An attachment could not be processed.')->danger()->send();
            }
        }

        TicketMailer::sendTicketCreated($this->record);
    }

    protected function getRedirectUrl(): string
    {
        // Redirect after successful creation
        return static::getResource()::getUrl('index');
    }
}
