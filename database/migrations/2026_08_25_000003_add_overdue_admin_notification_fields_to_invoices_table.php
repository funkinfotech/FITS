<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('overdue_admin_notified_at')->nullable()->after('overdue_reminder_sent_at');

            // Set when an admin dismisses the overdue nag for this invoice via
            // the "Ignore" action; suppresses further admin notifications.
            $table->timestamp('overdue_ignored_at')->nullable()->after('overdue_admin_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['overdue_admin_notified_at', 'overdue_ignored_at']);
        });
    }
};
