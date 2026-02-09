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
        // Step 1: Create student records for all users with role='student' who have orders
        // or for all student-role users (depending on your needs)
        $studentUsers = DB::table('users')->where('role', 'student')->get();

        foreach ($studentUsers as $user) {
            DB::table('students')->insertOrIgnore([
                'user_id' => $user->id,
                'instructor_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 2: Create a mapping of user_id to student_id
        // and update orders to reference the new student records

        // Drop the existing foreign key that references users table
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        // Update each order's student_id to reference the students table
        $orders = DB::table('orders')->get();
        foreach ($orders as $order) {
            $student = DB::table('students')->where('user_id', $order->student_id)->first();

            if ($student) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['student_id' => $student->id]);
            }
        }

        // Step 3: Add the new foreign key constraint referencing students table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the foreign key and index to students
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropIndex('orders_student_id_status_index');
        });

        // Revert orders.student_id back to users.id
        $orders = DB::table('orders')->get();
        foreach ($orders as $order) {
            $student = DB::table('students')->where('id', $order->student_id)->first();

            if ($student) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['student_id' => $student->user_id]);
            }
        }

        // Add back the original index (matching the original state - no FK, just index)
        Schema::table('orders', function (Blueprint $table) {
            $table->index('student_id', 'orders_student_id_foreign');
        });

        // Note: We don't delete student records in down() to preserve data
    }
};
