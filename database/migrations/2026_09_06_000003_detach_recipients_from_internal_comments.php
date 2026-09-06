<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Internal notes must never carry notification recipients. Earlier the
     * "Notify these contacts" list was saved even when a comment was marked
     * internal, so clear those pivot rows.
     */
    public function up(): void
    {
        DB::table('comment_contact')
            ->whereIn('comment_id', function ($query) {
                $query->select('id')->from('comments')->where('is_internal', true);
            })
            ->delete();
    }

    public function down(): void
    {
        // Irreversible data clean-up.
    }
};
