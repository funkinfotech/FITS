<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactListEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_only_primary_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@work.test', 'is_primary' => true]);
        $contact->emails()->create(['email' => 'jane@personal.test', 'is_primary' => false]);

        $this->actingAs($admin);

        Livewire::test(ListContacts::class)
            ->assertSee('jane@work.test')
            ->assertDontSee('jane@personal.test');
    }

    public function test_search_finds_contact_by_secondary_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
        $contact->emails()->create(['email' => 'jane@work.test', 'is_primary' => true]);
        $contact->emails()->create(['email' => 'jane@personal.test', 'is_primary' => false]);

        $this->actingAs($admin);

        Livewire::test(ListContacts::class)
            ->searchTable('jane@personal.test')
            ->assertCanSeeTableRecords([$contact]);
    }
}
