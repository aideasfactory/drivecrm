<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Instructor;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Notifications\InstructorLessonPaymentReceivedNotification;
use App\Notifications\LessonPaymentReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendPaymentReceivedEmailsAction
{
    /**
     * Send the itemised "payment received" email to the student (or their
     * contact) and the "learner paid" email to the instructor for one lesson
     * payment. Failures are logged and never thrown.
     */
    public function __invoke(LessonPayment $lessonPayment, ?Student $student, ?Instructor $instructor): void
    {
        if (! $student) {
            Log::warning('Payment received emails: no student found — skipping', [
                'lesson_payment_id' => $lessonPayment->id,
            ]);

            return;
        }

        $isBookedByContact = ! $student->owns_account;

        try {
            $recipientEmail = $isBookedByContact ? $student->contact_email : $student->email;

            if ($recipientEmail) {
                Notification::route('mail', $recipientEmail)
                    ->notify(new LessonPaymentReceivedNotification($lessonPayment, $student, $isBookedByContact));

                Log::info('Payment received email queued for student', [
                    'recipient_email' => $recipientEmail,
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            } else {
                Log::warning('Payment received emails: no student email — skipping student notification', [
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send student payment received email', [
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $instructorEmail = $instructor?->user?->email;

            if ($instructorEmail) {
                Notification::route('mail', $instructorEmail)
                    ->notify(new InstructorLessonPaymentReceivedNotification($lessonPayment, $student));

                Log::info('Payment received email queued for instructor', [
                    'instructor_email' => $instructorEmail,
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            } else {
                Log::warning('Payment received emails: no instructor email — skipping instructor notification', [
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send instructor payment received email', [
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
