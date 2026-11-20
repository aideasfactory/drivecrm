<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An instructor's request to permanently delete a past lesson, approved or
     * rejected by an owner. Approval hard-deletes the lesson, so every link to
     * it is nullable and the request keeps a snapshot of who, when and what.
     * `pending_lesson_id` holds the lesson id only while the request is
     * pending, so its unique index allows one pending request per lesson.
     */
    public function up(): void
    {
        Schema::create('lesson_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('pending_lesson_id')->nullable()->unique();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason');
            $table->string('instructor_name')->nullable();
            $table->string('student_name')->nullable();
            $table->date('lesson_date')->nullable();
            $table->time('lesson_start_time')->nullable();
            $table->time('lesson_end_time')->nullable();
            $table->json('lesson_snapshot')->nullable();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_deletion_requests');
    }
};
