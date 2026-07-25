<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Support\PortalAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalAccountProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_for_contact_creates_a_working_user(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
        ]);
        $contact->emails()->create(['email' => 'jane@acme.test', 'is_primary' => true]);

        $user = PortalAccountProvisioner::createForContact($contact, 'super-secret-password');

        $this->assertSame('Jane Doe', $user->name);
        $this->assertSame('jane@acme.test', $user->email);
        $this->assertSame($company->id, $user->company_id);
        $this->assertSame($contact->id, $user->contact_id);
        $this->assertTrue(Hash::check('super-secret-password', $user->password));
    }
}
