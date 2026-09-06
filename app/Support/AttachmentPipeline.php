<?php

namespace App\Support;

use App\Exceptions\RejectedAttachment;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns raw uploaded bytes into a stored, sanitised {@see Attachment}.
 *
 * Defence in depth:
 *   1. size + non-empty check
 *   2. filename sanitised; extension taken only from the last "." segment
 *   3. every "." segment checked against a hard deny-list
 *   4. extension must be on the allow-list
 *   5. real MIME sniffed from content (finfo) — the client's header is ignored
 *   6. content scanned for executable / script signatures
 *   7. sniffed MIME must match what the extension allows
 *   8. images are fully decoded and re-encoded by GD (strips metadata,
 *      destroys appended payloads); non-images are stored as-is
 *   9. stored on a private disk under a random ULID name, never the client name
 *
 * The file is only ever reachable through AttachmentController, which checks
 * the viewer can see the parent ticket and serves with locked-down headers.
 */
class AttachmentPipeline
{
    public static function fromUpload(
        UploadedFile $file,
        Model $attachable,
        ?User $uploader = null,
        ?Contact $contact = null,
    ): Attachment {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new RejectedAttachment('The upload could not be read.');
        }

        return static::store(
            $path,
            $file->getClientOriginalName(),
            $attachable,
            $uploader,
            $contact,
        );
    }

    /**
     * @param  string  $sourcePath  absolute path to the raw bytes on the local filesystem
     */
    public static function store(
        string $sourcePath,
        string $clientName,
        Model $attachable,
        ?User $uploader = null,
        ?Contact $contact = null,
    ): Attachment {
        $config = config('attachments');

        // 1. size
        $size = @filesize($sourcePath) ?: 0;

        if ($size <= 0) {
            throw new RejectedAttachment('The file is empty.');
        }

        if ($size > $config['max_size_kb'] * 1024) {
            throw new RejectedAttachment(sprintf(
                'The file is larger than the %s MB limit.',
                round($config['max_size_kb'] / 1024, 1),
            ));
        }

        // 2. clean name, 3. deny-list every segment
        $displayName = static::sanitizeName($clientName);
        $segments = array_map('strtolower', array_slice(explode('.', $displayName), 1));

        foreach ($segments as $segment) {
            if (in_array($segment, $config['blocked_extensions'], true)) {
                throw new RejectedAttachment('That file type is not allowed.');
            }
        }

        $extension = strtolower((string) pathinfo($displayName, PATHINFO_EXTENSION));

        // 4. allow-list
        if ($extension === '' || ! array_key_exists($extension, $config['allowed'])) {
            throw new RejectedAttachment('That file type is not allowed.');
        }

        // 5. sniff the real type from the bytes
        $mime = static::sniffMime($sourcePath);

        // 6. reject executable / markup / script content outright
        static::assertContentIsSafe($sourcePath, $mime);

        // 7. extension <-> content consistency
        if (! in_array($mime, $config['allowed'][$extension], true)) {
            throw new RejectedAttachment(
                "The file's contents don't match a .{$extension} file.",
            );
        }

        // 8. process
        $binary = null;
        $width = null;
        $height = null;

        if (in_array($extension, $config['reencode'], true)) {
            [$binary, $extension, $mime, $width, $height] = static::reencodeImage($sourcePath, $extension, $config);
        }

        // 9. store under a random name on the private disk
        $disk = $config['disk'];
        $path = sprintf(
            '%s/%s/%s.%s',
            trim($config['path'], '/'),
            now()->format('Y/m'),
            (string) Str::ulid(),
            $extension,
        );

        if ($binary !== null) {
            Storage::disk($disk)->put($path, $binary, 'private');
            $storedSize = strlen($binary);
            $checksum = hash('sha256', $binary);
        } else {
            Storage::disk($disk)->putFileAs(
                dirname($path),
                new File($sourcePath),
                basename($path),
                'private',
            );
            $storedSize = $size;
            $checksum = hash_file('sha256', $sourcePath);
        }

        /** @var Attachment $attachment */
        $attachment = $attachable->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => static::nameWithExtension($displayName, $extension),
            'extension' => $extension,
            'mime_type' => $mime,
            'size' => $storedSize,
            'checksum' => $checksum,
            'uploaded_by_user_id' => $uploader?->id,
            'uploaded_by_contact_id' => $contact?->id,
            'image_width' => $width,
            'image_height' => $height,
        ]);

        return $attachment;
    }

    public static function sniffMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($path) ?: 'application/octet-stream';
    }

    /**
     * Strip directories, control characters and leading dots; collapse
     * whitespace; cap the length. Never used as a storage path — display only.
     */
    public static function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = ltrim(trim($name), '.');
        $name = preg_replace('/[^\p{L}\p{N}._()\[\]#@+\-]/u', '_', $name) ?? $name;

        if ($name === '' || $name === '_') {
            $name = 'file';
        }

        if (mb_strlen($name) > 120) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 110) . ($extension ? ".{$extension}" : '');
        }

        return $name;
    }

    protected static function nameWithExtension(string $name, string $extension): string
    {
        $current = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($current === $extension) {
            return $name;
        }

        return pathinfo($name, PATHINFO_FILENAME) . ".{$extension}";
    }

    /**
     * Reject content that carries an executable or script signature, whatever
     * the extension claims.
     */
    protected static function assertContentIsSafe(string $path, string $mime): void
    {
        $blockedMimes = [
            'image/svg+xml', 'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml',
            'application/x-httpd-php', 'application/x-php', 'text/x-php',
            'application/javascript', 'text/javascript', 'application/x-javascript',
            'application/x-dosexec', 'application/x-mach-binary', 'application/x-executable',
            'application/x-msdownload', 'application/vnd.microsoft.portable-executable',
            'application/x-sharedlib', 'application/x-elf', 'application/hta',
        ];

        if (in_array($mime, $blockedMimes, true)) {
            throw new RejectedAttachment('That file type is not allowed.');
        }

        $head = (string) @file_get_contents($path, false, null, 0, 4096);

        if ($head === '') {
            return;
        }

        $binarySignatures = [
            "MZ",           // Windows PE
            "\x7FELF",      // Linux ELF
            "\xCA\xFE\xBA\xBE", // Mach-O / Java class
            "\xFE\xED\xFA", // Mach-O
            "#!",           // shebang script
            "<?php",
            "<?=",
            "<%",           // ASP / JSP
        ];

        foreach ($binarySignatures as $signature) {
            if (str_starts_with($head, $signature)) {
                throw new RejectedAttachment('That file type is not allowed.');
            }
        }

        $needles = ['<?php', '<script', '<%eval', '__HALT_COMPILER', 'phar://'];
        $haystack = strtolower($head);

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                throw new RejectedAttachment('That file type is not allowed.');
            }
        }
    }

    /**
     * Fully decode and re-encode a raster image with GD.
     *
     * @return array{0:string,1:string,2:string,3:int,4:int}  [binary, ext, mime, width, height]
     */
    protected static function reencodeImage(string $path, string $extension, array $config): array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RejectedAttachment('That file is not a readable image.');
        }

        [$width, $height] = $info;

        if (($width * $height) > $config['image_max_pixels']) {
            throw new RejectedAttachment('That image is too large to process.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw new RejectedAttachment('That file is not a readable image.');
        }

        try {
            ob_start();

            switch ($extension) {
                case 'png':
                    imagesavealpha($image, true);
                    imagepng($image, null, 6);
                    $mime = 'image/png';
                    break;

                case 'gif':
                    imagegif($image);
                    $mime = 'image/gif';
                    break;

                case 'webp':
                    imagesavealpha($image, true);
                    imagewebp($image, null, 85);
                    $mime = 'image/webp';
                    $extension = 'webp';
                    break;

                default: // jpg / jpeg
                    $flattened = imagecreatetruecolor($width, $height);
                    imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
                    imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);
                    imagejpeg($flattened, null, 85);
                    imagedestroy($flattened);
                    $mime = 'image/jpeg';
                    $extension = 'jpg';
                    break;
            }

            $binary = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if ($binary === '') {
            throw new RejectedAttachment('That image could not be processed.');
        }

        return [$binary, $extension, $mime, (int) $width, (int) $height];
    }
}
