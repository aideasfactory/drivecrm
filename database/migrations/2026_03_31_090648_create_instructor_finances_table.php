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
        Schema::create('instructor_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['payment', 'expense']);
            $table->string('description', 255);
            $table->integer('amount_pence');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // weekly, monthly, yearly
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'type']);
            $table->index(['instructor_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_finances');
    }
};
