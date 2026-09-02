<?php

namespace Tests\Feature;

use App\Mail\TicketAutoReplyMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InboundEmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function makeContact(Company $company, string $name, array $emails): Contact
    {
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        foreach ($emails as $index => $email) {
            $contact->emails()->create([
                'email' => $email,
                'is_primary' => $index === 0,
            ]);
        }

        return $contact;
    }

    protected function payload(array $overrides = []): array
    {
        return [
            'items' => [array_merge([
                'MessageId' => '<msg-1@example.com>',
                'From' => ['Address' => 'jane@acme.test', 'Name' => 'Jane Doe'],
                'To' => [['Address' => 'helpdesk@support.funkinfotech.com']],
                'Subject' => 'Printer is on fire',
                'ExtractedMarkdownMessage' => 'Please help, the printer is smoking.',
            ], $overrides)],
        ];
    }

    protected function secretHeader(): array
    {
        return ['X-Brevo-Webhook-Secret' => config('services.brevo.inbound_webhook_secret')];
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload(), [
            'X-Brevo-Webhook-Secret' => 'wrong-secret',
        ])->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_missing_secret_is_rejected(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_new_email_creates_ticket_matched_to_contact(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload(), $this->secretHeader())
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'email' => 'jane@acme.test',
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'source' => 'email',
            'subject' => 'Printer is on fire',
            'inbound_message_id' => '<msg-1@example.com>',
        ]);
    }

    public function test_new_email_from_secondary_address_still_matches_contact(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Asia King', ['asia@funkinfotech.com', 'asia.king0793@gmail.com']);

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-secondary@example.com>',
            'From' => ['Address' => 'asia.king0793@gmail.com', 'Name' => 'Asia King'],
        ]), $this->secretHeader())->assertOk();

        $this->assertDatabaseHas('tickets', [
            'email' => 'asia.king0793@gmail.com',
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'source' => 'email',
        ]);
    }

    public function test_new_email_from_unknown_sender_still_creates_ticket(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-2@example.com>',
            'From' => ['Address' => 'stranger@nowhere.test', 'Name' => 'A Stranger'],
        ]), $this->secretHeader())->assertOk();

        $this->assertDatabaseHas('tickets', [
            'email' => 'stranger@nowhere.test',
            'name' => 'A Stranger',
            'contact_id' => null,
            'company_id' => null,
            'source' => 'email',
        ]);
    }

    public function test_reply_to_plus_address_creates_comment_on_existing_ticket(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $ticket = Ticket::create([
            'ticket_number' => '12345678',
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Printer is on fire',
            'message' => 'Original message',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-3@example.com>',
            'To' => [['Address' => "helpdesk+{$ticket->ticket_number}@support.funkinfotech.com"]],
            'ExtractedMarkdownMessage' => 'Any update on this?',
        ]), $this->secretHeader())->assertOk();

        $this->assertDatabaseHas('comments', [
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'content' => 'Any update on this?',
            'is_internal' => false,
            'inbound_message_id' => '<msg-3@example.com>',
        ]);

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_html_entities_in_body_and_subject_are_decoded(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-entities@example.com>',
            'Subject' => 'Price is &lt; $5 &amp; needs review',
            'ExtractedMarkdownMessage' => 'If a &lt; b &amp;&amp; b &lt; c then it&#39;s broken.',
        ]), $this->secretHeader())->assertOk();

        $this->assertDatabaseHas('tickets', [
            'inbound_message_id' => '<msg-entities@example.com>',
            'subject' => 'Price is < $5 & needs review',
            'message' => "If a < b && b < c then it's broken.",
        ]);
    }

    public function test_new_email_from_known_contact_triggers_auto_reply(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload(), $this->secretHeader())
            ->assertOk();

        Mail::assertQueued(TicketAutoReplyMail::class, function (TicketAutoReplyMail $mail) use ($contact) {
            return $mail->hasTo($contact->email);
        });
    }

    public function test_new_email_from_unknown_sender_does_not_trigger_auto_reply(): void
    {
        Mail::fake();

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-unknown@example.com>',
            'From' => ['Address' => 'stranger@nowhere.test', 'Name' => 'A Stranger'],
        ]), $this->secretHeader())->assertOk();

        Mail::assertNotQueued(TicketAutoReplyMail::class);
    }

    public function test_reply_to_existing_ticket_does_not_trigger_auto_reply(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $ticket = Ticket::create([
            'ticket_number' => '87654321',
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Printer is on fire',
            'message' => 'Original message',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-reply@example.com>',
            'To' => [['Address' => "helpdesk+{$ticket->ticket_number}@support.funkinfotech.com"]],
            'ExtractedMarkdownMessage' => 'Any update on this?',
        ]), $this->secretHeader())->assertOk();

        Mail::assertNotQueued(TicketAutoReplyMail::class);
    }

    public function test_raw_html_body_is_converted_to_plain_text(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-html@example.com>',
            'ExtractedMarkdownMessage' => '<html><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">bleh@',
        ]), $this->secretHeader())->assertOk();

        $ticket = Ticket::where('inbound_message_id', '<msg-html@example.com>')->first();

        $this->assertNotNull($ticket);
        $this->assertSame('bleh@', $ticket->message);
    }

    public function test_html_body_with_block_tags_preserves_line_breaks(): void
    {
        $this->postJson(route('webhooks.brevo.inbound'), $this->payload([
            'MessageId' => '<msg-html-2@example.com>',
            'ExtractedMarkdownMessage' => '<html><body><p>First line.</p><p>Second line.</p></body></html>',
        ]), $this->secretHeader())->assertOk();

        $ticket = Ticket::where('inbound_message_id', '<msg-html-2@example.com>')->first();

        $this->assertNotNull($ticket);
        $this->assertSame("First line.\nSecond line.", $ticket->message);
    }

    public function test_duplicate_message_id_is_not_processed_twice(): void
    {
        $payload = $this->payload(['MessageId' => '<msg-dup@example.com>']);

        $this->postJson(route('webhooks.brevo.inbound'), $payload, $this->secretHeader())->assertOk();
        $this->postJson(route('webhooks.brevo.inbound'), $payload, $this->secretHeader())->assertOk();

        $this->assertDatabaseCount('tickets', 1);
    }
}
