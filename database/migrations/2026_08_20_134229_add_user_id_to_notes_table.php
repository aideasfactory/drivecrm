<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records which user wrote each note so the UI can show author name/avatar.
     * Nullable: notes created before this migration have no author.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('noteable_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
