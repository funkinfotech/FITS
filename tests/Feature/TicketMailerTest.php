<?php

namespace Tests\Feature;

use App\Mail\TicketReplyMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketMailerTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_reply_queues_one_email_per_recipient(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $contactA = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contactA->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $contactB = Contact::create(['company_id' => $company->id, 'name' => 'Bob Smith']);
        $contactB->emails()->create(['email' => 'bob@acme.test', 'is_primary' => true]);

        $ticket = Ticket::create([
            'ticket_number' => '11112222',
            'name' => $owner->name,
            'email' => $owner->email,
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Cannot print',
            'message' => 'Printer is broken',
            'user_id' => $owner->id,
            'company_id' => $company->id,
        ]);

        $comment = $ticket->comments()->create([
            'user_id' => $owner->id,
            'content' => 'We are looking into it.',
            'is_internal' => false,
        ]);

        $comment->recipients()->sync([$contactA->id, $contactB->id]);
        $comment->load('recipients');

        TicketMailer::sendReply($comment);

        Mail::assertQueued(TicketReplyMail::class, function (TicketReplyMail $mail) use ($contactA) {
            return $mail->hasTo($contactA->email);
        });

        Mail::assertQueued(TicketReplyMail::class, function (TicketReplyMail $mail) use ($contactB) {
            return $mail->hasTo($contactB->email);
        });

        Mail::assertQueued(TicketReplyMail::class, 2);
    }

    public function test_send_reply_does_nothing_when_no_recipients_selected(): void
    {
        Mail::fake();

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'ticket_number' => '33334444',
            'name' => $owner->name,
            'email' => $owner->email,
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Cannot print',
            'message' => 'Printer is broken',
            'user_id' => $owner->id,
        ]);

        $comment = $ticket->comments()->create([
            'user_id' => $owner->id,
            'content' => 'No recipients selected.',
            'is_internal' => false,
        ]);

        TicketMailer::sendReply($comment);

        Mail::assertNothingQueued();
    }
}
