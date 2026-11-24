<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonDeletionRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An instructor's request to permanently delete one of their past lessons
 * (logged by mistake, a duplicate, or one that never happened). An owner
 * approves (the lesson is hard-deleted) or rejects it. The request row is the
 * audit trail: it outlives the lesson and keeps a snapshot of it.
 */
class LessonDeletionRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'pending_lesson_id',
        'instructor_id',
        'student_id',
        'order_id',
        'requested_by_user_id',
        'status',
        'reason',
        'instructor_name',
        'student_name',
        'lesson_date',
        'lesson_start_time',
        'lesson_end_time',
        'lesson_snapshot',
        'requested_at',
        'decided_by_user_id',
        'decided_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LessonDeletionRequestStatus::class,
            'lesson_date' => 'date',
            'lesson_snapshot' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The lesson to delete. Null once the lesson has been deleted.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The instructor who asked for the lesson to be deleted.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * The pupil the lesson was for.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The login that made the request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * The owner who approved or rejected the request.
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === LessonDeletionRequestStatus::PENDING;
    }

    /**
     * The lesson's start time as stored ("H:i"), or null.
     */
    public function lessonStartTimeLabel(): ?string
    {
        return $this->lesson_start_time ? substr((string) $this->lesson_start_time, 0, 5) : null;
    }

    /**
     * The lesson's end time as stored ("H:i"), or null.
     */
    public function lessonEndTimeLabel(): ?string
    {
        return $this->lesson_end_time ? substr((string) $this->lesson_end_time, 0, 5) : null;
    }

    /**
     * @param  Builder<LessonDeletionRequest>  $query
     * @return Builder<LessonDeletionRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LessonDeletionRequestStatus::PENDING);
    }
}
