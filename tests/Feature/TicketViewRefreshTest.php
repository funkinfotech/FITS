<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketViewRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(): Ticket
    {
        return Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'Refresh Test',
            'email' => 'refresh@example.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Refresh test subject',
            'message' => 'Refresh test message',
        ]);
    }

    public function test_edit_ticket_page_reflects_status_changed_elsewhere_after_refresh_event(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->makeTicket();

        $this->actingAs($admin);

        $component = Livewire::test(EditTicket::class, ['record' => $ticket->id]);

        $ticket->update(['status' => 'Closed']);

        $component->call('refreshTicket')
            ->assertSet('data.status', 'Closed');
    }
}
