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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->string('noteable_type');
            $table->unsignedBigInteger('noteable_id');

            // Note content
            $table->text('note');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['noteable_type', 'noteable_id', 'deleted_at'], 'notes_noteable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
