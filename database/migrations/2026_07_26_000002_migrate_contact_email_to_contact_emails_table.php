<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contacts')->whereNotNull('email')->orderBy('id')->each(function ($contact) {
            DB::table('contact_emails')->insert([
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Data-only migration; no reliable reverse operation.
    }
};
