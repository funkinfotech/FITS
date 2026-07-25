<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(User $owner, ?Company $company = null): Ticket
    {
        return Ticket::create([
            'ticket_number' => (string) mt_rand(10000000, 99999999),
            'name' => $owner->name,
            'email' => $owner->email,
            'priority' => TicketPriority::Medium->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'Test subject',
            'message' => 'Test message',
            'user_id' => $owner->id,
            'company_id' => $company?->id,
        ]);
    }

    public function test_owner_can_view_their_own_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)
            ->get(route('tickets.show', $ticket))
            ->assertOk();
    }

    public function test_coworker_at_same_company_can_view_ticket(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        $ticket = $this->makeTicket($owner, $company);

        $this->actingAs($coworker)
            ->get(route('tickets.show', $ticket))
            ->assertOk();
    }

    public function test_user_at_different_company_cannot_view_ticket(): void
    {
        $companyA = Company::create(['name' => 'Acme Corp']);
        $companyB = Company::create(['name' => 'Other Corp']);
        $owner = User::factory()->create(['company_id' => $companyA->id]);
        $outsider = User::factory()->create(['company_id' => $companyB->id]);
        $ticket = $this->makeTicket($owner, $companyA);

        $this->actingAs($outsider)
            ->get(route('tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_unaffiliated_user_cannot_view_someone_elses_ticket(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($stranger)
            ->get(route('tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_coworker_can_comment_on_company_ticket(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        $ticket = $this->makeTicket($owner, $company);

        $this->actingAs($coworker)
            ->post(route('comments.store', $ticket), ['content' => 'Following up on this.'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $coworker->id,
        ]);
    }

    public function test_outsider_cannot_comment_on_ticket(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($stranger)
            ->post(route('comments.store', $ticket), ['content' => 'Nosy comment.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('comments', ['ticket_id' => $ticket->id]);
    }

    public function test_admin_can_view_ticket_with_no_owner_or_company(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = Ticket::create([
            'ticket_number' => (string) mt_rand(10000000, 99999999),
            'name' => 'Asia King',
            'email' => 'asia.king0793@gmail.com',
            'priority' => TicketPriority::Medium->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'Inbound email ticket',
            'message' => 'No matching contact or portal user.',
            'source' => 'email',
        ]);

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk();
    }

    public function test_non_admin_cannot_view_ticket_with_no_owner_or_company(): void
    {
        $stranger = User::factory()->create();
        $ticket = Ticket::create([
            'ticket_number' => (string) mt_rand(10000000, 99999999),
            'name' => 'Asia King',
            'email' => 'asia.king0793@gmail.com',
            'priority' => TicketPriority::Medium->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'Inbound email ticket',
            'message' => 'No matching contact or portal user.',
            'source' => 'email',
        ]);

        $this->actingAs($stranger)
            ->get(route('tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_dashboard_includes_tickets_from_coworkers(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        $ticket = $this->makeTicket($owner, $company);

        $response = $this->actingAs($coworker)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('tickets', function ($tickets) use ($ticket) {
            return $tickets->contains('id', $ticket->id);
        });
    }
}
