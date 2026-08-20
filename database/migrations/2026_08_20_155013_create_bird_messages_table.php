<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Individual messages belonging to mirrored Bird conversations, keyed by
     * Bird's message UUID so bird:sync upserts never duplicate.
     */
    public function up(): void
    {
        Schema::create('bird_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bird_conversation_id')
                ->constrained('bird_conversations')
                ->cascadeOnDelete();
            $table->string('sender_type')->default('');
            $table->string('sender_name')->default('');
            $table->text('text')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_messages');
    }
};
