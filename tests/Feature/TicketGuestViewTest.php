<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TicketGuestViewTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(): Ticket
    {
        return Ticket::create([
            'ticket_number' => (string) mt_rand(10000000, 99999999),
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'priority' => TicketPriority::Medium->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'Guest view test subject',
            'message' => 'Guest view test message',
        ]);
    }

    public function test_valid_signed_url_shows_the_ticket_without_login(): void
    {
        $ticket = $this->makeTicket();

        $url = URL::signedRoute('tickets.guest-view', ['ticket' => $ticket->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Guest view test subject');
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $ticket = $this->makeTicket();

        $this->get(route('tickets.guest-view', ['ticket' => $ticket->id]))
            ->assertForbidden();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $ticketA = $this->makeTicket();
        $ticketB = $this->makeTicket();

        $url = URL::signedRoute('tickets.guest-view', ['ticket' => $ticketA->id]);
        $tamperedUrl = str_replace((string) $ticketA->id, (string) $ticketB->id, $url);

        $this->get($tamperedUrl)->assertForbidden();
    }
}
