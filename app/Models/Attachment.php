<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class Attachment extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by_user_id',
        'uploaded_by_contact_id',
        'image_width',
        'image_height',
    ];

    protected $casts = [
        'size' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected static function booted(): void
    {
        // Never leave orphaned files on disk.
        static::deleting(function (Attachment $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function uploaderContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'uploaded_by_contact_id');
    }

    /** The ticket this attachment ultimately belongs to (directly or via a comment). */
    public function ticket(): ?Ticket
    {
        $parent = $this->attachable;

        return match (true) {
            $parent instanceof Ticket => $parent,
            $parent instanceof Comment => $parent->ticket,
            default => null,
        };
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /** Types we are willing to serve inline in the browser. */
    public function isInlineSafe(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
            || $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: $this->size >= 1_048_576 ? 1 : 0);
    }

    public function url(): string
    {
        return route('attachments.show', $this);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
