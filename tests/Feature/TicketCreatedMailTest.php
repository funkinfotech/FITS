<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Mail\TicketCreatedMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCreatedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_ticket_via_admin_sends_an_email_to_the_linked_contact(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $this->actingAs($admin);

        Livewire::test(CreateTicket::class)
            ->fillForm([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'priority' => 'Medium',
                'status' => 'Open',
                'subject' => 'Admin created ticket',
                'message' => 'Created on behalf of the customer.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Mail::assertQueued(TicketCreatedMail::class, function (TicketCreatedMail $mail) use ($contact) {
            return $mail->hasTo($contact->email) && $mail->ticket->subject === 'Admin created ticket';
        });
    }

    public function test_send_ticket_created_does_nothing_when_ticket_has_no_contact(): void
    {
        Mail::fake();

        $ticket = Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'No Contact',
            'email' => 'no-contact@example.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'No contact ticket',
            'message' => 'No contact linked.',
        ]);

        TicketMailer::sendTicketCreated($ticket);

        Mail::assertNothingQueued();
    }
}
