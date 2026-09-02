<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);

            // Day of month billing runs on. Capped at 28 so every month has that day.
            $table->unsignedTinyInteger('billing_day')->default(1);

            $table->boolean('is_active')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            // Last billing-cycle date an invoice was generated for; drives the
            // "next due" calculation so a late-running scheduler doesn't drift.
            $table->date('last_invoiced_on')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_charges');
    }
};
