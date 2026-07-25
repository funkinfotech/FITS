<?php

namespace Tests\Feature;

use App\Filament\Resources\CompanyResource\Pages\CreateCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyContactEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_with_a_contact_persists_the_contacts_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $component = Livewire::test(CreateCompany::class)
            ->set('data.name', 'Acme Corp');

        $contactKey = array_key_first($component->get('data.contacts'));
        $emailKey = array_key_first($component->get("data.contacts.{$contactKey}.emails"));

        $component
            ->set("data.contacts.{$contactKey}.name", 'Jane Doe')
            ->set("data.contacts.{$contactKey}.phone", '555-0100')
            ->set("data.contacts.{$contactKey}.emails.{$emailKey}.email", 'jane@acme.test')
            ->set("data.contacts.{$contactKey}.emails.{$emailKey}.is_primary", true)
            ->call('create')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'Acme Corp')->firstOrFail();
        $contact = $company->contacts()->firstOrFail();

        $this->assertSame('Jane Doe', $contact->name);
        $this->assertSame('jane@acme.test', $contact->email);
        $this->assertDatabaseHas('contact_emails', [
            'contact_id' => $contact->id,
            'email' => 'jane@acme.test',
            'is_primary' => true,
        ]);
    }
}
