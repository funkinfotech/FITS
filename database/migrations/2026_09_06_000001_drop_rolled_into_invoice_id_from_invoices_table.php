<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dropping the invoice-consolidation/rollover mechanic: invoices no longer
 * absorb an older unpaid invoice's total as a line item on themselves. Each
 * invoice stays an independent, immutable record; "previous balance" is now
 * a read-only summary computed from the company's other open invoices (see
 * Invoice::getPreviousBalanceAttribute()) rather than a stored relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rolled_into_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('rolled_into_invoice_id')->nullable()->after('recurring_charge_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }
};
