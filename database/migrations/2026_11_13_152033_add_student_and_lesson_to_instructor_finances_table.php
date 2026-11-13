<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which pupil (and lesson) an instructor's finance row was for, as recorded
     * by the legacy system. Bookkeeping only: nothing in the payment pipeline
     * reads these.
     */
    public function up(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
