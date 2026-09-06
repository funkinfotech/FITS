<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AttachmentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        $im = imagecreatetruecolor(30, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 0, 100, 200));
        imagepng($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, 'shot.png', 'image/png', null, true);
    }

    protected function ticketFor(?User $owner = null, ?Company $company = null): Ticket
    {
        return Ticket::create([
            'name' => 'C', 'email' => 'c@x.test', 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'S', 'message' => 'm', 'source' => 'email',
            'user_id' => $owner?->id,
            'company_id' => $company?->id,
        ]);
    }

    public function test_owner_can_view_their_attachment_inline(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($owner);
        $attachment = AttachmentPipeline::fromUpload($this->png(), $ticket, $owner);

        $response = $this->actingAs($owner)->get(route('attachments.show', $attachment));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $response->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('content-security-policy'));
    }

    public function test_a_stranger_cannot_view_it(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $stranger = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($owner);
        $attachment = AttachmentPipeline::fromUpload($this->png(), $ticket, $owner);

        $this->actingAs($stranger)->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_a_guest_cannot_view_it_without_a_signed_link(): void
    {
        $ticket = $this->ticketFor();
        $attachment = AttachmentPipeline::fromUpload($this->png(), $ticket);

        $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));

        // tampered / unsigned guest URL
        $this->get(route('attachments.guest-view', ['ticket' => $ticket, 'attachment' => $attachment]))
            ->assertForbidden();

        // valid signed URL works
        $signed = URL::signedRoute('attachments.guest-view', ['ticket' => $ticket, 'attachment' => $attachment]);
        $this->get($signed)->assertOk();
    }

    public function test_internal_note_attachment_is_hidden_from_customers_and_guests(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->ticketFor($customer);

        $note = Comment::create(['ticket_id' => $ticket->id, 'content' => 'x', 'is_internal' => true]);
        $attachment = AttachmentPipeline::fromUpload($this->png(), $note, $admin);

        $this->actingAs($customer)->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs($admin)->get(route('attachments.show', $attachment))->assertOk();

        $signed = URL::signedRoute('attachments.guest-view', ['ticket' => $ticket, 'attachment' => $attachment]);
        $this->get($signed)->assertNotFound();
    }

    public function test_download_query_forces_attachment_disposition(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($owner);
        $attachment = AttachmentPipeline::fromUpload($this->png(), $ticket, $owner);

        $response = $this->actingAs($owner)->get(route('attachments.show', $attachment) . '?download');

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_missing_file_is_a_404(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($owner);
        $attachment = AttachmentPipeline::fromUpload($this->png(), $ticket, $owner);

        Storage::disk($attachment->disk)->delete($attachment->path);

        $this->actingAs($owner)->get(route('attachments.show', $attachment))->assertNotFound();
    }
}
