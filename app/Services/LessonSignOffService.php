<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\Lesson\GetStudentLessonsAction;
use App\Actions\Student\Lesson\SignOffLessonAction;
use App\Mail\LessonFeedbackRequest;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class LessonSignOffService
{
    public function __construct(
        protected GetStudentLessonsAction $getStudentLessons,
        protected SignOffLessonAction $signOffLesson,
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Get all lessons for a student.
     */
    public function getStudentLessons(Student $student): Collection
    {
        return ($this->getStudentLessons)($student);
    }

    /**
     * Sign off a lesson: complete it, process payout, log activity, send feedback email.
     *
     * @return array{lesson: Lesson, payout: Payout, order_completed: bool}
     */
    public function signOffLesson(Lesson $lesson, Instructor $instructor): array
    {
        $lesson->load(['order.student']);

        // Execute the sign-off pipeline (mark complete, calendar update, payout, order check)
        $result = ($this->signOffLesson)($lesson, $instructor);

        $student = $lesson->order->student;
        $instructorName = $instructor->user?->name ?? 'Instructor';
        $lessonDate = $lesson->date?->format('d M Y') ?? 'N/A';

        // Log activity for both student and instructor
        ($this->logActivity)(
            $student,
            "Lesson on {$lessonDate} signed off by {$instructorName}",
            'lesson',
            [
                'lesson_id' => $lesson->id,
                'instructor_id' => $instructor->id,
                'payout_amount_pence' => $result['payout']->amount_pence,
            ]
        );

        ($this->logActivity)(
            $instructor,
            "Signed off lesson on {$lessonDate} for {$student->first_name} {$student->surname}",
            'lesson',
            [
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
                'payout_amount_pence' => $result['payout']->amount_pence,
            ]
        );

        // Send feedback request email to student
        $this->sendFeedbackEmail($lesson, $student, $instructor);

        return $result;
    }

    /**
     * Send a feedback request email to the student.
     */
    protected function sendFeedbackEmail(Lesson $lesson, Student $student, Instructor $instructor): void
    {
        $recipientEmail = $student->email ?? $student->user?->email;

        if (! $recipientEmail) {
            return;
        }

        Mail::to($recipientEmail)->queue(
            new LessonFeedbackRequest($lesson, $student, $instructor)
        );
    }
}
