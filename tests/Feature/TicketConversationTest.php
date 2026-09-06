<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(array $attributes = []): Ticket
    {
        return Ticket::create([
            'name' => 'Carl Customer',
            'email' => 'carl@acme.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Subject',
            'message' => 'The original customer message',
            'source' => 'email',
            ...$attributes,
        ]);
    }

    protected function comment(Ticket $ticket, array $attributes, string $createdAt): Comment
    {
        $comment = Comment::create([
            'ticket_id' => $ticket->id,
            'content' => 'content',
            'is_internal' => false,
            ...$attributes,
        ]);

        $comment->forceFill(['created_at' => $createdAt])->save();

        return $comment;
    }

    protected function render(Ticket $ticket): string
    {
        return Livewire::test(TicketCommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditTicket::class,
        ])->html();
    }

    public function test_newest_comment_first_and_original_message_last(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $ticket = $this->makeTicket();
        $this->comment($ticket, ['user_id' => $admin->id, 'content' => 'oldest-reply'], now()->subDays(2)->toDateTimeString());
        $this->comment($ticket, ['user_id' => $admin->id, 'content' => 'newest-reply'], now()->toDateTimeString());

        $html = $this->render($ticket);

        $newest = strpos($html, 'newest-reply');
        $oldest = strpos($html, 'oldest-reply');
        $original = strpos($html, 'The original customer message');

        $this->assertNotFalse($original);
        $this->assertLessThan($oldest, $newest);
        $this->assertLessThan($original, $oldest, 'the original message should come after every reply');
    }

    public function test_the_original_message_shows_even_with_no_replies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $ticket = $this->makeTicket();

        $html = $this->render($ticket);

        $this->assertStringContainsString('The original customer message', $html);
        $this->assertStringContainsString('Original message', $html);
        $this->assertStringContainsString('— Carl Customer', $html);
    }

    public function test_entries_render_as_cards_matching_the_message(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Ada Admin']);
        $this->actingAs($admin);

        $ticket = $this->makeTicket();
        $this->comment($ticket, ['user_id' => $admin->id, 'content' => 'A public reply'], now()->subHour()->toDateTimeString());
        $this->comment($ticket, ['user_id' => $admin->id, 'content' => 'A private thought', 'is_internal' => true], now()->toDateTimeString());

        $html = $this->render($ticket);

        // Two comments + the original message, all the same card.
        $this->assertSame(3, substr_count($html, 'ticket-message ticket-comment'));
        $this->assertStringContainsString('ticket-comment--internal', $html);
        $this->assertStringContainsString('ticket-comment--original', $html);
        $this->assertStringContainsString('— Ada Admin', $html);
    }

    public function test_a_contact_authored_reply_is_attributed_to_the_contact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Carl Customer']);
        $ticket = $this->makeTicket(['company_id' => $company->id, 'contact_id' => $contact->id]);
        $this->comment($ticket, ['contact_id' => $contact->id, 'user_id' => null, 'content' => 'Reply from the customer'], now()->toDateTimeString());

        $html = $this->render($ticket);

        $this->assertStringContainsString('— Carl Customer', $html);
    }
}
