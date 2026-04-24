<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mileage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('start_mileage');
            $table->unsignedInteger('end_mileage');
            $table->unsignedInteger('miles');
            $table->enum('type', ['business', 'personal']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'date']);
            $table->index(['instructor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mileage_logs');
    }
};
