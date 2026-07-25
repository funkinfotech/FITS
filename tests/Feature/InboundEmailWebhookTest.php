<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_duplicate_message_id_is_not_processed_twice(): void
    {
        $payload = $this->payload(['MessageId' => '<msg-dup@example.com>']);

        $this->postJson(route('webhooks.brevo.inbound'), $payload, $this->secretHeader())->assertOk();
        $this->postJson(route('webhooks.brevo.inbound'), $payload, $this->secretHeader())->assertOk();

        $this->assertDatabaseCount('tickets', 1);
    }
}
