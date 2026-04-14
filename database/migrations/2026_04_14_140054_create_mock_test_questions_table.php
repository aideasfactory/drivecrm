<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_test_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('item_code', 20)->unique();
            $table->string('category', 50)->index();
            $table->string('topic', 100)->index();
            $table->text('stem');
            $table->text('option_a')->nullable();
            $table->text('option_b')->nullable();
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->char('correct_answer', 1);
            $table->text('explanation')->nullable();
            $table->string('stem_image')->nullable();
            $table->string('option_a_image')->nullable();
            $table->string('option_b_image')->nullable();
            $table->string('option_c_image')->nullable();
            $table->string('option_d_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_test_questions');
    }
};
