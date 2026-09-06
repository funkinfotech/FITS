<?php

namespace Tests\Feature;

use App\Exceptions\RejectedAttachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AttachmentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/att-' . uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    protected function ticket(): Ticket
    {
        return Ticket::create([
            'name' => 'C', 'email' => 'c@x.test', 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'S', 'message' => 'm', 'source' => 'email',
        ]);
    }

    protected function pngBytes(int $w = 24, int $h = 24): string
    {
        $im = imagecreatetruecolor($w, $h);

        // Fill with noise so the file is comfortably larger than the 4 KB the
        // pipeline scans for signatures — this forces the re-encode path.
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                imagesetpixel($im, $x, $y, imagecolorallocate($im, ($x * 7) % 255, ($y * 13) % 255, ($x * $y) % 255));
            }
        }

        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    protected function upload(string $clientName, string $contents): UploadedFile
    {
        $path = $this->tmp . '/' . bin2hex(random_bytes(6));
        file_put_contents($path, $contents);

        return new UploadedFile($path, $clientName, null, null, true); // test mode, keeps real path
    }

    public function test_it_stores_and_re_encodes_an_image_dropping_appended_payload(): void
    {
        $ticket = $this->ticket();

        // A valid PNG (well over 4 KB) with a PHP web-shell appended after IEND.
        $base = $this->pngBytes(120, 120);
        $this->assertGreaterThan(4096, strlen($base));
        $polyglot = $base . "\n<?php system(\$_GET['c']); ?>";

        $attachment = AttachmentPipeline::fromUpload(
            $this->upload('shot.png', $polyglot),
            $ticket,
        );

        $this->assertSame('png', $attachment->extension);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame($ticket->id, $attachment->attachable_id);
        $this->assertSame(Ticket::class, $attachment->attachable_type);
        $this->assertNotNull($attachment->image_width);

        $stored = Storage::disk($attachment->disk)->get($attachment->path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertNotSame($polyglot, $stored, 'the file should have been re-encoded, not stored verbatim');
        $this->assertSame(hash('sha256', $stored), $attachment->checksum);
        $this->assertSame($attachment->size, strlen($stored));
        $this->assertNotFalse(@imagecreatefromstring($stored));
    }

    public function test_it_rejects_a_php_file(): void
    {
        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('evil.php', "<?php echo 'hi';"),
            $this->ticket(),
        );
    }

    public function test_it_rejects_a_php_payload_wearing_a_png_extension(): void
    {
        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('evil.png', "<?php system(\$_GET['c']);"),
            $this->ticket(),
        );
    }

    public function test_it_rejects_a_double_extension(): void
    {
        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('report.phar.png', $this->pngBytes()),
            $this->ticket(),
        );
    }

    public function test_it_rejects_svg(): void
    {
        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            $this->ticket(),
        );
    }

    public function test_it_rejects_an_oversized_file(): void
    {
        config(['attachments.max_size_kb' => 1]);

        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('big.txt', str_repeat('A', 4096)),
            $this->ticket(),
        );
    }

    public function test_it_rejects_a_fake_image(): void
    {
        $this->expectException(RejectedAttachment::class);

        AttachmentPipeline::fromUpload(
            $this->upload('not-really.png', 'just some text, definitely not a png'),
            $this->ticket(),
        );
    }

    public function test_it_accepts_a_pdf_unchanged(): void
    {
        $ticket = $this->ticket();
        $pdf = "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF";

        $attachment = AttachmentPipeline::fromUpload($this->upload('manual.pdf', $pdf), $ticket);

        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame($pdf, Storage::disk($attachment->disk)->get($attachment->path));
    }

    public function test_it_accepts_a_plain_text_log(): void
    {
        $attachment = AttachmentPipeline::fromUpload(
            $this->upload('debug.log', "line 1\nline 2\n"),
            $this->ticket(),
        );

        $this->assertSame('log', $attachment->extension);
        $this->assertSame('text/plain', $attachment->mime_type);
    }

    public function test_deleting_the_row_deletes_the_file(): void
    {
        $attachment = AttachmentPipeline::fromUpload($this->upload('a.png', $this->pngBytes()), $this->ticket());
        $disk = Storage::disk($attachment->disk);
        $path = $attachment->path;

        $this->assertTrue($disk->exists($path));
        $attachment->delete();
        $this->assertFalse($disk->exists($path));
    }

    public function test_filenames_are_sanitised_and_stored_under_random_names(): void
    {
        $attachment = AttachmentPipeline::fromUpload(
            $this->upload('../../etc/pas swd*.png', $this->pngBytes()),
            $this->ticket(),
        );

        $this->assertStringNotContainsString('/', $attachment->original_name);
        $this->assertStringNotContainsString('..', $attachment->original_name);
        $this->assertStringNotContainsString('*', $attachment->original_name);
        $this->assertStringContainsString('attachments/', $attachment->path);
        $this->assertMatchesRegularExpression('#/[0-9A-Za-z]{26}\.png$#', $attachment->path);
    }

    public function test_internal_comment_attachment_is_staff_only(): void
    {
        $ticket = $this->ticket();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);
        $ticket->update(['user_id' => $customer->id]);

        $note = Comment::create(['ticket_id' => $ticket->id, 'content' => 'x', 'is_internal' => true]);
        $attachment = AttachmentPipeline::fromUpload($this->upload('n.png', $this->pngBytes()), $note);

        $this->assertTrue($admin->can('view', $attachment));
        $this->assertFalse($customer->can('view', $attachment));
    }
}
