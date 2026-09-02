<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_charge_id')->nullable()->after('company_id')
                ->constrained()->nullOnDelete();

            // Set when this invoice's unpaid balance was carried forward as a
            // line item on a newer invoice for the same recurring charge.
            $table->foreignId('rolled_into_invoice_id')->nullable()->after('recurring_charge_id')
                ->constrained('invoices')->nullOnDelete();

            $table->timestamp('overdue_reminder_sent_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rolled_into_invoice_id');
            $table->dropConstrainedForeignId('recurring_charge_id');
            $table->dropColumn('overdue_reminder_sent_at');
        });
    }
};
