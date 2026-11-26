<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versions of the DRIVE+ Learner Terms shown in the student app. A row with
     * `published_at` NULL is the (single) editable draft; once published a row
     * is never edited again, so acceptances always point at the exact text the
     * learner agreed to. The current version is the latest published row.
     */
    public function up(): void
    {
        Schema::create('learner_terms_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->longText('body');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_terms_versions');
    }
};
