<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks when each portal user last opened a ticket, so the dashboard can
     * flag tickets that have a reply the user hasn't seen yet.
     */
    public function up(): void
    {
        Schema::create('ticket_user', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_viewed_at')->nullable();

            $table->primary(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_user');
    }
};
