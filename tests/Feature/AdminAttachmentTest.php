<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AttachmentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function ticket(): Ticket
    {
        return Ticket::create([
            'name' => 'C', 'email' => 'c@x.test', 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'S', 'message' => 'm', 'source' => 'email',
        ]);
    }

    protected function fakePng(string $name = 'screenshot.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40);
    }

    public function test_admin_can_attach_a_file_when_adding_a_comment(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $ticket = $this->ticket();

        Livewire::test(TicketCommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditTicket::class,
        ])->callTableAction('create', data: [
            'content' => 'Here is what I see',
            'is_internal' => false,
            'attachments' => [$this->fakePng()],
        ])->assertHasNoTableActionErrors();

        $comment = $ticket->comments()->latest('id')->first();
        $this->assertCount(1, $comment->attachments);
        $this->assertSame($admin->id, $comment->attachments->first()->uploaded_by_user_id);
        $this->assertSame('image/png', $comment->attachments->first()->mime_type);
    }

    public function test_deleting_a_comment_removes_its_attachments_and_files(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $ticket = $this->ticket();

        $comment = Comment::create(['ticket_id' => $ticket->id, 'user_id' => $admin->id, 'content' => 'x', 'is_internal' => false]);
        $path = tempnam(sys_get_temp_dir(), 'p');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $attachment = AttachmentPipeline::fromUpload(new UploadedFile($path, 'a.png', 'image/png', null, true), $comment, $admin);

        $disk = Storage::disk($attachment->disk);
        $stored = $attachment->path;
        $this->assertTrue($disk->exists($stored));

        $comment->delete();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertFalse($disk->exists($stored));
    }

    public function test_deleting_a_ticket_removes_all_attachment_files(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->ticket();
        $comment = Comment::create(['ticket_id' => $ticket->id, 'user_id' => $admin->id, 'content' => 'x', 'is_internal' => false]);

        $mk = function ($parent) use ($admin) {
            $path = tempnam(sys_get_temp_dir(), 'p');
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

            return AttachmentPipeline::fromUpload(new UploadedFile($path, 'a.png', 'image/png', null, true), $parent, $admin);
        };

        $a1 = $mk($ticket);
        $a2 = $mk($comment);
        $disk = Storage::disk($a1->disk);

        $ticket->delete();

        $this->assertFalse($disk->exists($a1->path));
        $this->assertFalse($disk->exists($a2->path));
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_admin_conversation_renders_attachment_thumbnails(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $ticket = $this->ticket();

        $path = tempnam(sys_get_temp_dir(), 'p');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        AttachmentPipeline::fromUpload(new UploadedFile($path, 'shot.png', 'image/png', null, true), $ticket, $admin);

        $html = Livewire::test(TicketCommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditTicket::class,
        ])->html();

        $this->assertStringContainsString('ticket-att__thumb', $html);
        $this->assertStringContainsString('/attachments/', $html);
    }
}
