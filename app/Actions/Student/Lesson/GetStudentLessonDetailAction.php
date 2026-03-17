<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetStudentLessonDetailAction
{
    /**
     * Fetch a single lesson belonging to a student, with full relationships.
     *
     * Ensures the lesson belongs to the student via one of their orders.
     *
     * @throws ModelNotFoundException
     */
    public function __invoke(Student $student, int $lessonId): Lesson
    {
        return Lesson::query()
            ->whereIn('order_id', $student->orders()->select('id'))
            ->with([
                'instructor.user:id,name',
                'order:id,package_name,package_id,payment_mode',
                'order.package:id,name',
                'calendarItem.calendar:id,date',
                'lessonPayment:id,lesson_id,amount_pence,status,paid_at',
                'payout:id,lesson_id,status,amount_pence,stripe_transfer_id,paid_at',
            ])
            ->findOrFail($lessonId);
    }
}
