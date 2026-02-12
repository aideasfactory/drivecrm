<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->string('loggable_type');
            $table->unsignedBigInteger('loggable_id');

            // Activity details
            $table->string('category', 50)->index();
            $table->text('message');
            $table->json('metadata')->nullable();

            // Soft deletes
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index(['loggable_type', 'loggable_id', 'deleted_at'], 'activity_logs_loggable_index');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
