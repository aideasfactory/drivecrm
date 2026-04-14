<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->index();
            $table->string('topic', 100)->nullable()->index();
            $table->unsignedSmallInteger('total_questions')->default(50);
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_tests');
    }
};
