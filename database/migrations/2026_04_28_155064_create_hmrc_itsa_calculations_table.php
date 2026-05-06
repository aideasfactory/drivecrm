<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nino', 32);
            $table->string('tax_year', 16);
            $table->string('calculation_id', 128)->index();
            $table->string('calculation_type', 32);
            $table->string('status', 16);
            $table->timestamp('triggered_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('summary_payload')->nullable();
            $table->json('detail_payload')->nullable();
            $table->json('error_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'calculation_id']);
            $table->index(['user_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_calculations');
    }
};
