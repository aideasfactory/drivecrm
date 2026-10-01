<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Instructor;
use App\Models\Order;
use App\Services\InstructorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignStudentOnPaidBookingAction
{
    public function __construct(
        protected InstructorService $instructorService,
    ) {}

    /**
     * Add the learner to the booking instructor's contact list once the first
     * payment is confirmed.
     *
     * Checkout creates the student and the hold without setting
     * `students.instructor_id`, so an unpaid checkout never appears in the
     * instructor's pupils list. A learner who already belongs to this
     * instructor is left as they are. A learner who belongs to someone else
     * moves to the instructor they have just paid.
     */
    public function __invoke(Order $order): void
    {
        $order->loadMissing('student');

        $student = $order->student;
        $instructorId = $order->instructor_id;

        if ($student === null || $instructorId === null || $student->instructor_id === $instructorId) {
            return;
        }

        $previousInstructorId = $student->instructor_id;

        $student->update(['instructor_id' => $instructorId]);

        $student->logActivity(
            'Added to instructor contact list after payment',
            'instructor_assigned',
            [
                'instructor_id' => $instructorId,
                'previous_instructor_id' => $previousInstructorId,
                'order_id' => $order->id,
            ],
        );

        $instructor = Instructor::query()->find($instructorId);
        $previousInstructor = $previousInstructorId !== null
            ? Instructor::query()->find($previousInstructorId)
            : null;

        DB::afterCommit(function () use ($instructor, $previousInstructor): void {
            if ($instructor) {
                $this->instructorService->invalidateStudentCache($instructor);
            }

            if ($previousInstructor) {
                $this->instructorService->invalidateStudentCache($previousInstructor);
            }
        });

        Log::info('Learner added to instructor contact list after payment', [
            'order_id' => $order->id,
            'student_id' => $student->id,
            'instructor_id' => $instructorId,
            'previous_instructor_id' => $previousInstructorId,
        ]);
    }
}
