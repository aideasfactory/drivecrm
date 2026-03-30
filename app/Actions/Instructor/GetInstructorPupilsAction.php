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
     * @param  Instructor  $instructor  The instructor to fetch pupils for
     * @param  string|null  $search  Optional search term (name, email, phone)
     * @param  string  $status  Filter by student status ('active' by default, 'all' to show everyone)
     * @return Collection Formatted pupil data
     */
    public function __invoke(Instructor $instructor, ?string $search = null, string $status = 'active'): Collection
    {
        $query = Student::where('instructor_id', $instructor->id)
            ->with(['user', 'orders.lessons']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

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

        return $query->get()->map(function (Student $student) {
            $activeOrder = $student->orders->first();
            $lessonsTotal = 0;
            $lessonsCompleted = 0;
            $revenuePence = 0;

            foreach ($student->orders as $order) {
                $lessonsTotal += $order->lessons->count();
                $lessonsCompleted += $order->lessons->where('status', LessonStatus::COMPLETED)->count();
                $revenuePence += $order->package_total_price_pence ?? 0;
            }

            // Find next upcoming lesson (today or future, pending status)
            $today = Carbon::today();
            $nextLesson = null;
            foreach ($student->orders as $order) {
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
                'user_id' => $student->user_id,
                'name' => $name,
                'email' => $student->email ?? $student->user?->email,
                'phone' => $student->phone,
                'lessons_completed' => $lessonsCompleted,
                'lessons_total' => $lessonsTotal,
                'next_lesson_date' => $nextLesson?->date?->format('Y-m-d'),
                'next_lesson_time' => $nextLesson?->start_time?->format('H:i'),
                'revenue_pence' => $revenuePence,
                'has_app' => $student->user_id !== null,
                'status' => $student->status ?? 'active',
            ];
        });
    }
}
