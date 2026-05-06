<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_quarterly_update_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarterly_update_id');
            $table->foreign('quarterly_update_id', 'hmrc_itsa_qu_rev_qu_id_fk')
                ->references('id')->on('hmrc_itsa_quarterly_updates')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreign('user_id', 'hmrc_itsa_qu_rev_user_id_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('kind', 32); // submission | amendment | failed_submission | failed_amendment
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->string('submission_id', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by_user_id');
            $table->foreign('submitted_by_user_id', 'hmrc_itsa_qu_rev_submitter_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('digital_records_attested_at')->nullable();
            $table->timestamps();

            $table->unique(['quarterly_update_id', 'revision_number'], 'hmrc_itsa_qu_rev_unique');
            $table->index(['user_id', 'submitted_at'], 'hmrc_itsa_qu_rev_user_subm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_quarterly_update_revisions');
    }
};
