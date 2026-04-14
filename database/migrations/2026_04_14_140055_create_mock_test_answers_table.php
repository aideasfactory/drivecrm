<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_test_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mock_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_test_question_id')->constrained()->cascadeOnDelete();
            $table->char('selected_answer', 1);
            $table->boolean('is_correct');
            $table->timestamps();

            $table->unique(['mock_test_id', 'mock_test_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_test_answers');
    }
};
