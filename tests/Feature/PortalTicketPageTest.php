<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PortalTicketPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    protected function ticket(User $user): Ticket
    {
        return Ticket::create([
            'name' => $user->name, 'email' => $user->email, 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'Printer down', 'message' => 'ORIGINAL_PROBLEM', 'source' => 'portal', 'user_id' => $user->id,
        ]);
    }

    public function test_reply_box_sits_above_the_thread_and_the_original_message_is_last(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticket($user);

        $this->actingAs($user)->get(route('tickets.show', $ticket)); // baseline
        $this->travel(1)->minutes();
        Comment::create(['ticket_id' => $ticket->id, 'user_id' => null, 'content' => 'NEWER_REPLY', 'is_internal' => false]);
        $this->travel(1)->minutes();
        Comment::create(['ticket_id' => $ticket->id, 'user_id' => null, 'content' => 'NEWEST_REPLY', 'is_internal' => false]);

        $html = $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk()->content();

        $reply = strpos($html, 'Add a Reply');
        $conversation = strpos($html, 'Conversation');
        $newest = strpos($html, 'NEWEST_REPLY');
        $newer = strpos($html, 'NEWER_REPLY');
        $original = strpos($html, 'ORIGINAL_PROBLEM');

        $this->assertLessThan($conversation, $reply, 'reply box should be above the conversation');
        $this->assertLessThan($newer, $newest, 'newest reply first');
        $this->assertLessThan($original, $newer, 'original message comes after every reply');
        $this->assertStringContainsString('Original message', $html);
    }

    public function test_the_original_message_can_still_be_edited_from_the_thread(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticket($user);

        $this->actingAs($user)
            ->patch(route('tickets.update', $ticket), [
                'subject' => 'Printer really down',
                'message' => 'updated body',
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertSame('Printer really down', $ticket->fresh()->subject);
        $this->assertSame('updated body', $ticket->fresh()->message);
    }

    public function test_guest_view_also_ends_with_the_original_message(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticket($user);
        Comment::create(['ticket_id' => $ticket->id, 'user_id' => null, 'content' => 'A_REPLY', 'is_internal' => false]);

        $html = $this->get(URL::signedRoute('tickets.guest-view', ['ticket' => $ticket]))->assertOk()->content();

        $this->assertLessThan(strpos($html, 'ORIGINAL_PROBLEM'), strpos($html, 'A_REPLY'));
        $this->assertStringNotContainsString('Add a Reply', $html); // guests reply by email
    }
}
