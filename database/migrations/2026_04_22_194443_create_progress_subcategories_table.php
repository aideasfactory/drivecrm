<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progress_category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['progress_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_subcategories');
    }
};
