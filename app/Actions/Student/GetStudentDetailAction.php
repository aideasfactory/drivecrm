<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class GetStudentDetailAction
{
    public function __construct(
        protected CalculateStudentRevenueAction $calculateStudentRevenue
    ) {}
    /**
     * Get detailed student data for the pupil detail page header.
     *
     * @param  Student  $student  The student to fetch details for
     * @return array Formatted student detail data
     */
    public function __invoke(Student $student): array
    {
        $student->load(['user', 'instructor', 'orders.lessons.lessonPayment']);

        $name = $student->first_name && $student->surname
            ? $student->first_name.' '.$student->surname
            : ($student->user?->name ?? 'Unknown');

        $lessonsTotal = 0;
        $lessonsCompleted = 0;

        foreach ($student->orders as $order) {
            $lessonsTotal += $order->lessons->count();
            $lessonsCompleted += $order->lessons->where('status', 'completed')->count();
        }

        $revenuePence = ($this->calculateStudentRevenue)($student);

        return [
            'id' => $student->id,
            'user_id' => $student->user_id,
            'instructor_id' => $student->instructor_id,
            'name' => $name,
            'first_name' => $student->first_name,
            'surname' => $student->surname,
            'email' => $student->email ?? $student->user?->email,
            'phone' => $student->phone,
            'has_app' => $student->user_id !== null,
            'lessons_completed' => $lessonsCompleted,
            'lessons_total' => $lessonsTotal,
            'revenue_pence' => $revenuePence,
            'status' => $student->status ?? 'active',
            'student_status' => $student->status ?? 'active',
            'inactive_reason' => $student->inactive_reason,
        ];
    }
}
