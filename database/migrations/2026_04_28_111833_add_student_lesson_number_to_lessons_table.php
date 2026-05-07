<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedInteger('student_lesson_number')->nullable()->after('mileage');
        });

        DB::transaction(function (): void {
            DB::table('students')->orderBy('id')->chunkById(200, function ($students): void {
                foreach ($students as $student) {
                    $orderIds = DB::table('orders')
                        ->where('student_id', $student->id)
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->pluck('id');

                    if ($orderIds->isEmpty()) {
                        continue;
                    }

                    $lessons = DB::table('lessons')
                        ->whereIn('order_id', $orderIds)
                        ->orderByRaw('FIELD(order_id, '.$orderIds->implode(',').')')
                        ->orderBy('id')
                        ->pluck('id');

                    $number = 1;
                    foreach ($lessons as $lessonId) {
                        DB::table('lessons')
                            ->where('id', $lessonId)
                            ->update(['student_lesson_number' => $number]);
                        $number++;
                    }
                }
            });
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedInteger('student_lesson_number')->nullable(false)->change();
            $table->index('student_lesson_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['student_lesson_number']);
            $table->dropColumn('student_lesson_number');
        });
    }
};
