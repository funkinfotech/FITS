<?php

namespace App\Support;

use App\Exceptions\RejectedAttachment;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls attachments off an inbound Brevo email and runs each one through
 * {@see AttachmentPipeline}. Brevo doesn't inline the bytes — it gives a
 * download token that we exchange (authenticated) for the file.
 *
 * Failures are logged, never thrown: a dodgy attachment must not stop the
 * ticket/reply from being created.
 */
class BrevoInboundAttachments
{
    private const ENDPOINT = 'https://api.brevo.com/v3/inbound/attachments/';

    public static function importInto(Model $attachable, array $emailItem, ?Contact $contact = null): void
    {
        $attachments = $emailItem['Attachments'] ?? $emailItem['attachments'] ?? [];

        if (! is_array($attachments) || $attachments === []) {
            return;
        }

        $apiKey = config('services.brevo.key');

        if (! $apiKey) {
            Log::warning('Inbound email has attachments but BREVO_API_KEY is not set — skipping them.');

            return;
        }

        $maxBytes = config('attachments.max_size_kb') * 1024;
        $limit = config('attachments.max_files');

        foreach (array_slice(array_values($attachments), 0, $limit) as $meta) {
            $token = $meta['DownloadToken'] ?? null;
            $name = $meta['Name'] ?? 'attachment';
            $declaredSize = (int) ($meta['ContentLength'] ?? 0);

            if (! $token) {
                continue;
            }

            if ($declaredSize > 0 && $declaredSize > $maxBytes) {
                Log::info("Inbound attachment '{$name}' exceeds the size limit — skipped.");

                continue;
            }

            try {
                $response = Http::withHeaders([
                    'api-key' => $apiKey,
                    'accept' => 'application/octet-stream',
                ])->timeout(30)->get(self::ENDPOINT . $token);

                if (! $response->successful()) {
                    Log::warning('Failed to download an inbound attachment from Brevo.', [
                        'name' => $name,
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                $body = $response->body();

                if ($body === '' || strlen($body) > $maxBytes) {
                    continue;
                }

                $tmp = tempnam(sys_get_temp_dir(), 'brevo-att-');
                file_put_contents($tmp, $body);

                try {
                    AttachmentPipeline::store($tmp, $name, $attachable, null, $contact);
                } finally {
                    @unlink($tmp);
                }
            } catch (RejectedAttachment $e) {
                Log::info("Inbound attachment '{$name}' rejected: {$e->getMessage()}");
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
