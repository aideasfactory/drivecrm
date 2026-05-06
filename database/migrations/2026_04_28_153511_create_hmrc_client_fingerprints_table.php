<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_client_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmrc_token_id')
                ->unique()
                ->constrained('hmrc_tokens')
                ->cascadeOnDelete();
            $table->json('screens');
            $table->json('window_size');
            $table->json('timezone');
            $table->text('browser_user_agent');
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_client_fingerprints');
    }
};
