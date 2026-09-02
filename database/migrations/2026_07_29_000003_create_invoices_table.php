<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('Draft');
            $table->date('issue_date');
            $table->date('due_date');

            // Bill-to snapshot, captured at creation time so later edits to the
            // customer record never retroactively alter a historical invoice.
            $table->string('bill_to_name')->nullable();
            $table->text('bill_to_address')->nullable();

            // From/branding snapshot, captured from BusinessProfile at creation time
            // for the same immutability reason.
            $table->string('from_business_name')->nullable();
            $table->text('from_address')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_phone')->nullable();
            $table->string('from_tax_id')->nullable();
            $table->text('from_bank_details')->nullable();
            $table->string('from_logo_path')->nullable();

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total', 10, 2)->default(0);

            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
