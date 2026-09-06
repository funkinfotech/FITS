<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable'); // Ticket or Comment

            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type', 191);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64); // sha256 of the stored bytes

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
