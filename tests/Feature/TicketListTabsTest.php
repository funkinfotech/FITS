<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketListTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(string $status): Ticket
    {
        return Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'Tab Test',
            'email' => 'tab-test@example.test',
            'priority' => 'Medium',
            'status' => $status,
            'subject' => 'Tab test subject',
            'message' => 'Tab test message',
        ]);
    }

    public function test_closed_tab_shows_a_badge_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makeTicket('Closed');
        $this->makeTicket('Closed');
        $this->makeTicket('Open');

        $this->actingAs($admin);

        $tabs = (new ListTickets())->getTabs();

        $this->assertSame(2, $tabs['closed']->getBadge());
    }
}
