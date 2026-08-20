<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local read-only mirror of Bird AI Employee conversations. Rows are
     * upserted by bird:sync using Bird's own conversation UUID as the primary
     * key, so re-syncing is idempotent.
     */
    public function up(): void
    {
        Schema::create('bird_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contact_name')->default('');
            $table->string('contact_email')->default('');
            $table->string('contact_phone')->default('');
            $table->text('last_message')->nullable();
            $table->string('status')->default('');
            $table->uuid('channel_id')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_conversations');
    }
};
