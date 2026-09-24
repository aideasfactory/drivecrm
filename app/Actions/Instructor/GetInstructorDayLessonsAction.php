<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\LessonStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GetInstructorDayLessonsAction
{
    /**
     * Fetch all lessons for an instructor on a specific date.
     *
     * Returns lessons with related student, order, calendar item,
     * payment, payout, and reflective log data — structured for
     * a day-view in the mobile app.
     */
    public function __invoke(Instructor $instructor, string $date): Collection
    {
        return $this->lessonsQuery($instructor)
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->orderBy('date')
            ->get();
    }

    /**
     * Lessons across an inclusive date range, ordered by date then start time.
     *
     * Same set as the single-day query for each date. Null dates are omitted.
     */
    public function between(Instructor $instructor, string $from, string $to): Collection
    {
        return $this->lessonsQuery($instructor)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return HasMany<Lesson, Instructor>
     */
    private function lessonsQuery(Instructor $instructor): HasMany
    {
        return $instructor->lessons()
            // Exclude drafts (awaiting payment) and cancelled lessons — a cancelled
            // lesson has had its calendar slot freed, so it must not appear in the
            // instructor's day view (mirrors the admin diary, which never shows it).
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
            ]);
    }
}
