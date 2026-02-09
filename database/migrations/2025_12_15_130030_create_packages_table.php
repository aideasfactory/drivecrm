<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('total_price_pence');
            $table->integer('lessons_count');
            $table->integer('lesson_price_pence');
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'instructor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
