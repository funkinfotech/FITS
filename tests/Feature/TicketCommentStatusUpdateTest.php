<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCommentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(): Ticket
    {
        return Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'Status Update Test',
            'email' => 'status-update@example.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Status update test subject',
            'message' => 'Status update test message',
        ]);
    }

    public function test_sending_a_comment_updates_ticket_status_via_dropdown(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->makeTicket();

        $this->actingAs($admin);

        Livewire::test(TicketCommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditTicket::class,
        ])->callTableAction('create', data: [
            'content' => 'Closing this out.',
            'is_internal' => false,
            'ticket_status' => 'Closed',
        ])->assertHasNoTableActionErrors();

        $this->assertSame('Closed', $ticket->fresh()->status->value);
    }

    public function test_edit_ticket_page_can_also_still_update_status_directly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->makeTicket();

        $this->actingAs($admin);

        Livewire::test(EditTicket::class, ['record' => $ticket->id])
            ->fillForm(['status' => 'Closed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Closed', $ticket->fresh()->status->value);
    }
}
