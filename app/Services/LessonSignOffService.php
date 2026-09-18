<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\Lesson\ComputeLessonCardStatusAction;
use App\Actions\Student\Lesson\GetStudentLessonDetailAction;
use App\Actions\Student\Lesson\GetStudentLessonsAction;
use App\Actions\Student\Lesson\SaveLessonSummaryAction;
use App\Actions\Student\Lesson\SignOffLessonAction;
use App\Jobs\ProcessResourceRecommendationsJob;
use App\Mail\LessonFeedbackRequest;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use App\Models\Student;
use App\Notifications\LessonSignedOffNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class LessonSignOffService extends BaseService
{
    public function __construct(
        protected GetStudentLessonsAction $getStudentLessons,
        protected GetStudentLessonDetailAction $getStudentLessonDetail,
        protected ComputeLessonCardStatusAction $computeCardStatus,
        protected SignOffLessonAction $signOffLesson,
        protected SaveLessonSummaryAction $saveLessonSummary,
        protected LogActivityAction $logActivity,
        protected OrderService $orderService
    ) {}

    /**
     * Get lessons for a student, optionally filtered.
     *
     * @param  array{status?: string, from_date?: string, sort?: string, limit?: int, include_drafts?: bool}  $filters
     */
    public function getStudentLessons(Student $student, array $filters = []): Collection
    {
        return ($this->getStudentLessons)($student, $filters);
    }

    /**
     * Get a single lesson belonging to a student with full relationships and computed card status.
     */
    public function getLessonDetail(Student $student, int $lessonId): Lesson
    {
        $lesson = ($this->getStudentLessonDetail)($student, $lessonId);

        $lesson->setAttribute('card_status', ($this->computeCardStatus)($lesson, $student)->value);

        return $lesson;
    }

    /**
     * Sign off a lesson: save summary, complete it, process payout, log activity, send emails, dispatch resource recommendations.
     *
     * @return array{lesson: Lesson, payout: Payout, order_completed: bool}
     */
    public function signOffLesson(Lesson $lesson, Instructor $instructor, string $summary = ''): array
    {
        $lesson->load(['order.student']);

        // Save the instructor's lesson summary before sign-off
        if ($summary !== '') {
            ($this->saveLessonSummary)($lesson, $summary);
        }

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

        // Send lesson signed off confirmation to the instructor
        $this->sendLessonSignedOffNotification($lesson, $student, $instructor);

        // Send feedback request email to student
        $this->sendFeedbackEmail($lesson, $student, $instructor);

        // Trigger the next weekly invoice + payment-link email immediately. The Service
        // method no-ops for non-weekly orders. Wrapped in try/catch so a Stripe failure
        // never blocks lesson sign-off.
        try {
            $this->orderService->sendNextDueInvoice($lesson->order);
        } catch (\Exception $e) {
            Log::error('Failed to send next weekly invoice on lesson sign-off', [
                'lesson_id' => $lesson->id,
                'order_id' => $lesson->order_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Dispatch AI resource recommendations (separate async job), delayed so
        // the email doesn't land alongside the feedback request
        if ($summary !== '') {
            ProcessResourceRecommendationsJob::dispatch($lesson)
                ->delay(now()->addMinutes((int) config('lessons.recommendations_delay_minutes')));
        }

        return $result;
    }

    /**
     * Send the lesson signed off confirmation to the instructor, and log
     * notification activity. Students intentionally no longer receive this
     * email — they already get the feedback request (and weekly payers the
     * next invoice), so the sign-off notice added noise without value.
     */
    protected function sendLessonSignedOffNotification(Lesson $lesson, Student $student, Instructor $instructor): void
    {
        $lessonDate = $lesson->date?->format('d M Y') ?? 'N/A';

        $instructorUser = $instructor->user;
        if ($instructorUser?->email) {
            Notification::send(
                $instructorUser,
                new LessonSignedOffNotification($lesson, $student, $instructor, true)
            );

            ($this->logActivity)(
                $instructor,
                "Lesson signed off confirmation sent to {$instructorUser->email} for lesson on {$lessonDate}",
                'notification',
                [
                    'type' => 'lesson_signed_off',
                    'lesson_id' => $lesson->id,
                    'student_id' => $student->id,
                    'recipient_email' => $instructorUser->email,
                ]
            );
        }
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

        $lessonDate = $lesson->date?->format('d M Y') ?? 'N/A';

        ($this->logActivity)(
            $student,
            "Feedback request email sent to {$recipientEmail} for lesson on {$lessonDate}",
            'notification',
            [
                'type' => 'lesson_feedback_request',
                'lesson_id' => $lesson->id,
                'recipient_email' => $recipientEmail,
            ]
        );
    }
}
