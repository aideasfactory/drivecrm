<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_token_refresh_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('outcome', [
                'success',
                'failure_invalid_grant',
                'failure_network',
                'failure_other',
            ]);
            $table->string('error_code')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['outcome', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_token_refresh_logs');
    }
};
