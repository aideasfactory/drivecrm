<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A learner's acceptance of one Learner Terms version in the student app.
     * One row per student per version. `accepted_at` is stored in UTC and
     * shown in UK time. A version can't be deleted while it has acceptances.
     */
    public function up(): void
    {
        Schema::create('learner_terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_terms_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('platform', 10);
            $table->string('app_version', 30)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'learner_terms_version_id'], 'lta_student_version_unique');
            $table->index(['student_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_terms_acceptances');
    }
};
