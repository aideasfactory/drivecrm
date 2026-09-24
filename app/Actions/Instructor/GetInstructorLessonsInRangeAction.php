<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\LessonStatus;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorLessonsInRangeAction
{
    /**
     * Fetch all lessons for an instructor between two dates (inclusive).
     *
     * Returns lessons with related student, order, calendar item,
     * payment, payout, and reflective log data — structured for the
     * day and week views in the mobile app. Ordered by date, then start time.
     */
    public function __invoke(Instructor $instructor, string $from, string $to): Collection
    {
        return $instructor->lessons()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            // Exclude drafts (awaiting payment) and cancelled lessons — a cancelled
            // lesson has had its calendar slot freed, so it must not appear in the
            // instructor's diary (mirrors the admin diary, which never shows it).
            ->whereNotIn('status', [LessonStatus::DRAFT, LessonStatus::CANCELLED])
            ->with([
                'order' => fn ($query) => $query->select([
                    'id', 'student_id', 'instructor_id', 'package_id',
                    'package_name', 'package_lesson_price_pence', 'payment_mode', 'status',
                ]),
                'order.student:id,user_id,first_name,surname,email,phone,status',
                'order.student.user:id,name,email',
                'calendarItem' => fn ($query) => $query->select([
                    'id', 'calendar_id', 'start_time', 'end_time',
                    'is_available', 'status', 'item_type', 'notes',
                ]),
                'calendarItem.calendar:id,instructor_id,date',
                'lessonPayment:id,lesson_id,amount_pence,status,paid_at',
                'payout:id,lesson_id,status,amount_pence,paid_at',
                'reflectiveLog:id,lesson_id',
                'resources:id,title,resource_type',
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }
}
