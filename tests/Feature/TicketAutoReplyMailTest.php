<?php

namespace Tests\Feature;

use App\Mail\TicketAutoReplyMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Support\TicketMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketAutoReplyMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_auto_reply_queues_mail_with_contacts_first_name(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $ticket = Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Printer is on fire',
            'message' => 'Please help.',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        TicketMailer::sendAutoReply($ticket);

        Mail::assertQueued(TicketAutoReplyMail::class, function (TicketAutoReplyMail $mail) use ($contact, $ticket) {
            return $mail->hasTo($contact->email)
                && $mail->ticket->is($ticket)
                && str_contains($mail->render(), 'Hi Jane,');
        });
    }

    public function test_send_auto_reply_does_nothing_when_ticket_has_no_contact(): void
    {
        Mail::fake();

        $ticket = Ticket::create([
            'ticket_number' => (string) random_int(10000000, 99999999),
            'name' => 'A Stranger',
            'email' => 'stranger@nowhere.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Unknown sender ticket',
            'message' => 'No contact linked.',
        ]);

        TicketMailer::sendAutoReply($ticket);

        Mail::assertNothingQueued();
    }
}
