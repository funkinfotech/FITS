<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadRepliesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    protected function ticketFor(User $owner, ?Company $company = null): Ticket
    {
        return Ticket::create([
            'name' => $owner->name, 'email' => $owner->email, 'priority' => 'Medium', 'status' => 'Open',
            'subject' => 'My ticket', 'message' => 'm', 'source' => 'portal',
            'user_id' => $owner->id, 'company_id' => $company?->id,
        ]);
    }

    protected function reply(Ticket $ticket, ?User $author): Comment
    {
        return Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author?->id,
            'content' => 'reply',
            'is_internal' => false,
        ]);
    }

    public function test_a_staff_reply_marks_a_ticket_unread_on_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($user);

        $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk(); // baseline

        $this->travel(1)->minutes();
        $this->reply($ticket, null); // staff reply after

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('New reply')
            ->assertViewHas('tickets', fn ($t) => $t->firstWhere('id', $ticket->id)->unread_count === 1);
    }

    public function test_opening_the_ticket_clears_the_unread_flag(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($user);

        $this->travel(1)->minutes();
        $this->reply($ticket, null);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('New reply');

        $this->travel(1)->minutes();
        $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertDontSee('New reply')
            ->assertViewHas('tickets', fn ($t) => $t->firstWhere('id', $ticket->id)->unread_count === 0);
    }

    public function test_a_users_own_reply_does_not_count_as_unread(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($user);
        $this->actingAs($user)->get(route('tickets.show', $ticket)); // baseline

        $this->travel(1)->minutes();
        $this->actingAs($user)->post(route('comments.store', $ticket), ['content' => 'my own follow-up']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertViewHas('tickets', fn ($t) => $t->firstWhere('id', $ticket->id)->unread_count === 0);
    }

    public function test_a_brand_new_ticket_you_just_submitted_is_not_unread(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '10101010',
            'subject' => 'Fresh ticket',
            'priority' => 'Medium',
            'message' => 'hello',
        ])->assertRedirect();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('New reply');
    }

    public function test_the_show_page_flags_replies_that_arrived_since_last_visit(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $ticket = $this->ticketFor($user);

        $this->reply($ticket, null); // present on the first visit
        $this->actingAs($user)->get(route('tickets.show', $ticket));

        $this->travel(1)->minutes();
        $this->reply($ticket, null); // arrived after

        $html = $this->actingAs($user)->get(route('tickets.show', $ticket))->content();
        $this->assertStringContainsString('>New</span>', $html);
        $this->assertStringContainsString('bg-primary-50', $html);
    }

    public function test_a_coworkers_reply_shows_as_unread_to_other_coworkers(): void
    {
        $company = Company::create(['name' => 'Acme']);
        $alice = User::factory()->create(['company_id' => $company->id]);
        $bob = User::factory()->create(['company_id' => $company->id]);
        $ticket = $this->ticketFor($alice, $company);

        $this->actingAs($bob)->get(route('tickets.show', $ticket)); // bob sees it

        $this->travel(1)->minutes();
        $this->reply($ticket, $alice); // alice replies

        $this->actingAs($bob)->get(route('dashboard'))
            ->assertViewHas('tickets', fn ($t) => $t->firstWhere('id', $ticket->id)->unread_count === 1);
    }
}
