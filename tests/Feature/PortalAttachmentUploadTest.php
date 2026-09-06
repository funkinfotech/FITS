<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortalAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function png(string $name = 'shot.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        $im = imagecreatetruecolor(20, 20);
        imagefill($im, 0, 0, imagecolorallocate($im, 20, 90, 160));
        imagepng($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function fakePhp(string $name = 'x.php'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($path, "<?php echo 'pwned';");

        return new UploadedFile($path, $name, 'text/x-php', null, true);
    }

    public function test_a_new_ticket_can_carry_attachments(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '55555555',
            'subject' => 'Broken thing',
            'priority' => 'Medium',
            'message' => 'See screenshots',
            'attachments' => [$this->png('one.png'), $this->png('two.png')],
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::firstWhere('subject', 'Broken thing');
        $this->assertNotNull($ticket);
        $this->assertCount(2, $ticket->attachments);
        $this->assertSame($user->id, $ticket->attachments->first()->uploaded_by_user_id);
        $this->assertSame(Ticket::class, $ticket->attachments->first()->attachable_type);
    }

    public function test_a_reply_can_carry_attachments(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = Ticket::create([
            'name' => $user->name, 'email' => $user->email, 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'S', 'message' => 'm', 'source' => 'portal', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('comments.store', $ticket), [
            'content' => 'Here is a log',
            'attachments' => [$this->png()],
        ])->assertRedirect();

        $comment = $ticket->comments()->latest('id')->first();
        $this->assertCount(1, $comment->attachments);
    }

    public function test_a_disallowed_extension_is_rejected_at_validation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '66666666',
            'subject' => 'Nope',
            'priority' => 'Medium',
            'message' => 'trying to sneak a shell',
            'attachments' => [$this->fakePhp()],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('tickets', 0); // validation failed before the ticket was created
    }

    public function test_a_payload_that_passes_validation_but_fails_the_pipeline_warns_without_losing_the_ticket(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // .png extension (passes the extension rule) but PHP content (pipeline rejects).
        $path = tempnam(sys_get_temp_dir(), 'poly');
        file_put_contents($path, "<?php system(\$_GET['c']);");
        $sneaky = new UploadedFile($path, 'sneaky.png', 'image/png', null, true);

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '77777777',
            'subject' => 'Sneaky',
            'priority' => 'Medium',
            'message' => 'hi',
            'attachments' => [$sneaky],
        ]);

        $response->assertRedirect(route('tickets.index'));
        $response->assertSessionHas('warning');

        $ticket = Ticket::firstWhere('subject', 'Sneaky');
        $this->assertNotNull($ticket);          // ticket still created
        $this->assertCount(0, $ticket->attachments); // but nothing stored
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_too_many_files_is_rejected(): void
    {
        config(['attachments.max_files' => 2]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '88888888',
            'subject' => 'Lots',
            'priority' => 'Medium',
            'message' => 'hi',
            'attachments' => [$this->png(), $this->png(), $this->png()],
        ])->assertSessionHasErrors('attachments');
    }
}
