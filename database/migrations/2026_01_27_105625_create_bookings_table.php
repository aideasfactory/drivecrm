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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->onDelete('cascade');
            $table->foreignId('instructor_id')
                ->constrained('instructors')
                ->onDelete('cascade');
            $table->foreignId('package_id')
                ->constrained('packages')
                ->onDelete('cascade');
            $table->foreignId('calendar_item_id')
                ->constrained('calendar_items')
                ->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('confirmed');
            $table->uuid('enquiry_id')->nullable();
            $table->timestamps();

            $table->index('enquiry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
