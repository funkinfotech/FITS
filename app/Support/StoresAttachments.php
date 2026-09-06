<?php

namespace App\Support;

use App\Exceptions\RejectedAttachment;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared upload handling for the customer-facing controllers. The heavy lifting
 * (sanitising, MIME sniffing, image re-encoding, storage) lives in
 * {@see AttachmentPipeline}; this just runs it over an "attachments[]" field and
 * surfaces per-file rejections without failing the whole request.
 */
trait StoresAttachments
{
    /**
     * Validation rules for an optional `attachments[]` upload field. Cheap
     * checks only — the pipeline does the deep inspection.
     *
     * @return array<string, mixed>
     */
    protected function attachmentRules(): array
    {
        $config = config('attachments');

        return [
            'attachments' => ['nullable', 'array', 'max:' . $config['max_files']],
            'attachments.*' => [
                'file',
                'max:' . $config['max_size_kb'],
                Rule::file()->extensions(array_keys($config['allowed'])),
            ],
        ];
    }

    /**
     * Store every uploaded file against $attachable.
     *
     * @return array<int, string>  human-readable messages for files that were rejected
     */
    protected function storeAttachments(
        Request $request,
        Model $attachable,
        ?User $uploader = null,
        ?Contact $contact = null,
    ): array {
        $rejected = [];

        foreach ($request->file('attachments', []) as $file) {
            try {
                AttachmentPipeline::fromUpload($file, $attachable, $uploader, $contact);
            } catch (RejectedAttachment $e) {
                $rejected[] = AttachmentPipeline::sanitizeName($file->getClientOriginalName())
                    . ' — ' . $e->getMessage();
            } catch (\Throwable $e) {
                report($e);
                $rejected[] = AttachmentPipeline::sanitizeName($file->getClientOriginalName())
                    . ' — could not be processed.';
            }
        }

        return $rejected;
    }

    /**
     * Flash a warning listing any rejected files, or return the redirect
     * untouched if everything stored cleanly.
     */
    protected function withAttachmentWarning(mixed $redirect, array $rejected): mixed
    {
        if ($rejected === []) {
            return $redirect;
        }

        return $redirect->with(
            'warning',
            'Some files were not attached: ' . implode('  •  ', $rejected),
        );
    }
}
