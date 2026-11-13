<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pupil's theory or practical test and its result. Filled by the data
     * import for now; the UI comes later.
     */
    public function up(): void
    {
        Schema::create('student_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('calendar_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('test_centre')->nullable();
            $table->string('result', 16);
            $table->unsignedSmallInteger('driving_faults')->nullable();
            $table->unsignedSmallInteger('serious_faults')->nullable();
            $table->unsignedSmallInteger('dangerous_faults')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tests');
    }
};
