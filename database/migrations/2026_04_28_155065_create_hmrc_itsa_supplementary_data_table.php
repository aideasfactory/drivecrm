<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_supplementary_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tax_year', 16);
            $table->string('type', 32);
            $table->json('payload');
            $table->string('submission_id', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tax_year', 'type'], 'hmrc_itsa_supp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_supplementary_data');
    }
};
