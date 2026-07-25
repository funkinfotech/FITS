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

    public function test_contact_belongs_to_company_and_company_has_many_contacts(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '555-0100',
        ]);

        $this->assertTrue($contact->company->is($company));
        $this->assertTrue($company->contacts->contains($contact));
    }

    public function test_ticket_submission_auto_links_matching_contact(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create([
            'email' => 'jane@acme.test',
            'company_id' => $company->id,
        ]);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
        ]);

        $this->actingAs($user)->post(route('tickets.store'), [
            'ticket_number' => '12345678',
            'subject' => 'Cannot print - matching contact case',
            'message' => 'The printer is on fire.',
            'priority' => TicketPriority::Medium->value,
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::where('subject', 'Cannot print - matching contact case')->firstOrFail();
        $this->assertSame($contact->id, $ticket->contact_id);
    }

    public function test_ticket_submission_leaves_contact_null_when_no_match(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::factory()->create([
            'email' => 'someone-else@acme.test',
            'company_id' => $company->id,
        ]);
        Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
        ]);

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
