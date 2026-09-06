<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundEmailJob;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InboundEmailAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-key']);
        Mail::fake();
    }

    protected function pngBytes(): string
    {
        $im = imagecreatetruecolor(24, 24);
        imagefill($im, 0, 0, imagecolorallocate($im, 5, 90, 150));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    protected function item(array $overrides = []): array
    {
        return array_merge([
            'MessageId' => '<msg-att-1@example.com>',
            'From' => ['Address' => 'jane@acme.test', 'Name' => 'Jane Doe'],
            'To' => [['Address' => 'helpdesk@support.funkinfotech.com']],
            'Subject' => 'Screenshot attached',
            'ExtractedMarkdownMessage' => 'See attached screenshot.',
        ], $overrides);
    }

    public function test_it_imports_a_valid_attachment_from_a_new_email(): void
    {
        $png = $this->pngBytes();

        Http::fake([
            'api.brevo.com/v3/inbound/attachments/tok-1' => Http::response($png, 200),
        ]);

        (new ProcessInboundEmailJob($this->item([
            'Attachments' => [
                ['Name' => 'error.png', 'ContentType' => 'image/png', 'ContentLength' => strlen($png), 'DownloadToken' => 'tok-1'],
            ],
        ])))->handle();

        $ticket = Ticket::firstWhere('subject', 'Screenshot attached');
        $this->assertNotNull($ticket);
        $this->assertCount(1, $ticket->attachments);
        $this->assertSame('image/png', $ticket->attachments->first()->mime_type);

        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-key'));
    }

    public function test_it_imports_an_attachment_onto_a_reply(): void
    {
        $ticket = Ticket::create([
            'name' => 'Jane', 'email' => 'jane@acme.test', 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'Existing', 'message' => 'm', 'source' => 'email',
        ]);
        $number = $ticket->ticket_number;
        $png = $this->pngBytes();

        Http::fake([
            'api.brevo.com/*' => Http::response($png, 200),
        ]);

        (new ProcessInboundEmailJob($this->item([
            'MessageId' => '<reply-att@example.com>',
            'To' => [['Address' => "helpdesk+{$number}@support.funkinfotech.com"]],
            'Attachments' => [
                ['Name' => 'more.png', 'ContentType' => 'image/png', 'ContentLength' => strlen($png), 'DownloadToken' => 'tok-r'],
            ],
        ])))->handle();

        $comment = $ticket->comments()->latest('id')->first();
        $this->assertCount(1, $comment->attachments);
    }

    public function test_a_malicious_inbound_attachment_is_dropped_without_failing_the_email(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response("<?php system(\$_GET['c']);", 200),
        ]);

        (new ProcessInboundEmailJob($this->item([
            'Attachments' => [
                ['Name' => 'invoice.pdf', 'ContentType' => 'application/pdf', 'ContentLength' => 40, 'DownloadToken' => 'tok-x'],
            ],
        ])))->handle();

        $ticket = Ticket::firstWhere('subject', 'Screenshot attached');
        $this->assertNotNull($ticket);            // the email still became a ticket
        $this->assertCount(0, $ticket->attachments); // but the payload was not stored
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_it_skips_attachments_when_the_api_key_is_missing(): void
    {
        config(['services.brevo.key' => null]);
        Http::fake();

        (new ProcessInboundEmailJob($this->item([
            'Attachments' => [
                ['Name' => 'x.png', 'ContentType' => 'image/png', 'ContentLength' => 10, 'DownloadToken' => 'tok-1'],
            ],
        ])))->handle();

        $this->assertDatabaseCount('attachments', 0);
        Http::assertNothingSent();
    }
}
