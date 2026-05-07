<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_final_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nino', 32);
            $table->string('tax_year', 16);
            $table->foreignId('calculation_id')->nullable();
            $table->foreign('calculation_id', 'hmrc_itsa_fd_calc_fk')
                ->references('id')->on('hmrc_itsa_calculations')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->string('correlation_id', 128)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('digital_records_attested_at')->nullable();
            $table->foreignId('digital_records_attested_by_user_id')->nullable();
            $table->foreign('digital_records_attested_by_user_id', 'hmrc_itsa_fd_attested_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tax_year'], 'hmrc_itsa_fd_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_final_declarations');
    }
};
