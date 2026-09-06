<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return $this->serve($request, $attachment);
    }

    /**
     * Serve an attachment to a guest via a signed link (the ticket "guest view").
     * Attachments on internal notes are never exposed here.
     */
    public function guest(Request $request, Ticket $ticket, Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;

        abort_unless($attachment->ticket()?->is($ticket) ?? false, 404);
        abort_if($parent instanceof Comment && $parent->is_internal, 404);

        return $this->serve($request, $attachment);
    }

    protected function serve(Request $request, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        $disposition = (! $request->has('download') && $attachment->isInlineSafe())
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $disk->response($attachment->path, $attachment->original_name, [
            // Trust our stored type, never let the browser sniff a different one.
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300, no-transform',
            // Belt-and-suspenders for anything that somehow isn't what we think:
            // no scripts, no subresources, no framing. (Kept lenient enough for
            // the browser's native image / PDF viewers.)
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; object-src 'none'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ], $disposition);
    }
}
