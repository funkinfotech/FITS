<?php

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::whereDoesntHave('contacts')->each(function (Company $company) {
            $user = $company->users()->oldest()->first();

            Contact::create([
                'company_id' => $company->id,
                'name' => $user->name ?? 'Primary Contact (please update)',
                'email' => $user->email ?? "contact-{$company->id}@placeholder.invalid",
                'phone' => $company->phone,
            ]);
        });
    }

    public function down(): void
    {
        // Data-only migration; no reliable reverse operation.
    }
};
