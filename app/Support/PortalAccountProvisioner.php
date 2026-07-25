<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PortalAccountProvisioner
{
    public static function createForContact(Contact $contact, string $password): User
    {
        return User::create([
            'name' => $contact->name,
            'email' => $contact->email,
            'password' => Hash::make($password),
            'company_id' => $contact->company_id,
            'contact_id' => $contact->id,
        ]);
    }
}
