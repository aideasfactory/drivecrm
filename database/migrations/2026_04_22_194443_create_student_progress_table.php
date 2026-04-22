<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('progress_subcategory_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['student_id', 'progress_subcategory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};
