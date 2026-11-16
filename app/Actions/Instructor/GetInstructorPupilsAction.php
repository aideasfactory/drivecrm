<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\LessonStatus;
use App\Models\Instructor;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GetInstructorPupilsAction
{
    /**
     * Get all students belonging to an instructor with computed stats.
     *
     * Also includes pupils transferred to another instructor while a lesson
     * this instructor taught is still waiting for sign-off, flagged
     * `transferred_away` (whatever the status filter), so the instructor can
     * open and sign it off. Those rows only carry this instructor's own lesson
     * counts: no contact details, revenue or next lesson.
     *
     * @param  Instructor  $instructor  The instructor to fetch pupils for
     * @param  string|null  $search  Optional search term (name, email, phone)
     * @param  string  $status  Filter by student status ('active' by default, 'all' to show everyone)
     * @return Collection Formatted pupil data
     */
    public function __invoke(Instructor $instructor, ?string $search = null, string $status = 'active'): Collection
    {
        $query = Student::query()
            ->where(function ($q) use ($instructor, $status) {
                $q->where(function ($own) use ($instructor, $status) {
                    $own->where('instructor_id', $instructor->id)
                        ->when($status !== 'all', fn ($filtered) => $filtered->where('status', $status));
                })->orWhere(function ($transferred) use ($instructor) {
                    $transferred->where(fn ($other) => $other
                        ->whereNull('instructor_id')
                        ->orWhere('instructor_id', '!=', $instructor->id))
                        ->whereHas('orders.lessons', fn ($lessons) => $lessons->awaitingSignOffBy($instructor->id));
                });
            })
            ->with(['user', 'orders.lessons.lessonPayment']);

        if ($search) {
            $searchTerm = '%'.$search.'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('surname', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('phone', 'like', $searchTerm)
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        return $query->get()->map(function (Student $student) use ($instructor) {
            $transferredAway = $student->instructor_id !== $instructor->id;
            $lessonsTotal = 0;
            $lessonsCompleted = 0;

            foreach ($student->orders as $order) {
                $lessons = $transferredAway
                    ? $order->lessons->where('instructor_id', $instructor->id)
                    : $order->lessons;

                $lessonsTotal += $lessons->count();
                $lessonsCompleted += $lessons->where('status', LessonStatus::COMPLETED)->count();
            }

            // Find next upcoming lesson (today or future, pending status)
            $today = Carbon::today();
            $nextLesson = null;
            foreach ($transferredAway ? [] : $student->orders as $order) {
                foreach ($order->lessons as $lesson) {
                    if ($lesson->status === LessonStatus::PENDING && $lesson->date && $lesson->date->gte($today)) {
                        if (! $nextLesson || $lesson->date->lt($nextLesson->date)) {
                            $nextLesson = $lesson;
                        }
                    }
                }
            }

            $name = $student->first_name && $student->surname
                ? $student->first_name.' '.$student->surname
                : ($student->user?->name ?? 'Unknown');

            return [
                'id' => $student->id,
                'user_id' => $transferredAway ? null : $student->user_id,
                'name' => $name,
                'avatar' => $student->avatar,
                'email' => $transferredAway ? null : ($student->email ?? $student->user?->email),
                'phone' => $transferredAway ? null : $student->phone,
                'lessons_completed' => $lessonsCompleted,
                'lessons_total' => $lessonsTotal,
                'next_lesson_date' => $nextLesson?->date?->format('Y-m-d'),
                'next_lesson_time' => $nextLesson?->start_time?->format('H:i'),
                'revenue_pence' => $transferredAway ? 0 : $student->total_revenue_pence,
                'has_app' => $student->app_last_active_at !== null,
                'has_test_pass_guarantee' => $student->hasTestPassGuarantee(),
                'status' => $student->status ?? 'active',
                'transferred_away' => $transferredAway,
            ];
        });
    }
}
