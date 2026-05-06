<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_id', 64)->index();
            $table->string('period_key', 64)->index();
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->date('due_date');
            $table->date('received_date')->nullable();
            $table->string('status', 16);
            $table->string('obligation_type', 64)->default('Quarterly Update');
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'business_id', 'period_key', 'obligation_type'], 'hmrc_itsa_oblig_unique');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_obligations');
    }
};
