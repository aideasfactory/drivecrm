<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class GetStudentDetailAction
{
    /**
     * Get detailed student data for the pupil detail page header.
     *
     * @param  Student  $student  The student to fetch details for
     * @return array Formatted student detail data
     */
    public function __invoke(Student $student): array
    {
        $student->load(['user', 'instructor', 'orders.lessons']);

        $name = $student->first_name && $student->surname
            ? $student->first_name.' '.$student->surname
            : ($student->user?->name ?? 'Unknown');

        $lessonsTotal = 0;
        $lessonsCompleted = 0;
        $revenuePence = 0;

        foreach ($student->orders as $order) {
            $lessonsTotal += $order->lessons->count();
            $lessonsCompleted += $order->lessons->where('status', 'completed')->count();
            $revenuePence += $order->package_total_price_pence ?? 0;
        }

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
            'status' => $this->determineStatus($lessonsCompleted, $lessonsTotal, $student->orders->first()),
        ];
    }

    private function determineStatus(int $completed, int $total, $activeOrder): string
    {
        if (! $activeOrder) {
            return 'pending';
        }

        if ($activeOrder->status === 'cancelled') {
            return 'cancelled';
        }

        if ($total > 0 && $completed >= $total) {
            return 'completed';
        }

        if ($activeOrder->status === 'active') {
            return 'active';
        }

        return 'pending';
    }
}
