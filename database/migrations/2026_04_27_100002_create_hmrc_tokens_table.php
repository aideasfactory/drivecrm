<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->string('token_type')->default('bearer');
            $table->json('scopes');
            $table->timestamp('expires_at');
            $table->timestamp('refresh_expires_at');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_expiry_warning_at')->nullable();
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_tokens');
    }
};
