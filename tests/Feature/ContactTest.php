<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
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

    public function test_contact_belongs_to_company_and_company_has_many_contacts(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $this->assertTrue($contact->company->is($company));
        $this->assertTrue($company->contacts->contains($contact));
    }

    public function test_email_accessor_returns_primary_email(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test', 'jane.personal@gmail.test']);

        $this->assertSame('jane@acme.test', $contact->email);
    }

    public function test_ticket_submission_auto_links_matching_contact(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create([
            'email' => 'jane@acme.test',
            'company_id' => $company->id,
        ]);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '12345678',
            'subject' => 'Cannot print - matching contact case',
            'message' => 'The printer is on fire.',
            'priority' => TicketPriority::Medium->value,
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::where('subject', 'Cannot print - matching contact case')->firstOrFail();
        $this->assertSame($contact->id, $ticket->contact_id);
    }

    public function test_ticket_submission_auto_links_via_secondary_email(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create([
            'email' => 'jane.personal@gmail.test',
            'company_id' => $company->id,
        ]);
        $contact = $this->makeContact($company, 'Jane Doe', ['jane@acme.test', 'jane.personal@gmail.test']);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '12345679',
            'subject' => 'Cannot print - secondary email case',
            'message' => 'The printer is on fire.',
            'priority' => TicketPriority::Medium->value,
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::where('subject', 'Cannot print - secondary email case')->firstOrFail();
        $this->assertSame($contact->id, $ticket->contact_id);
    }

    public function test_ticket_submission_leaves_contact_null_when_no_match(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create([
            'email' => 'someone-else@acme.test',
            'company_id' => $company->id,
        ]);
        $this->makeContact($company, 'Jane Doe', ['jane@acme.test']);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '87654321',
            'subject' => 'Cannot print - no matching contact case',
            'message' => 'The printer is on fire.',
            'priority' => TicketPriority::Medium->value,
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::where('subject', 'Cannot print - no matching contact case')->firstOrFail();
        $this->assertNull($ticket->contact_id);
    }
}
