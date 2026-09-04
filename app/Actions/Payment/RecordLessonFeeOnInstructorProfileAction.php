<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\InstructorFinance;
use App\Models\LessonPayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class RecordLessonFeeOnInstructorProfileAction
{
    /**
     * Persist the lesson-fee portion of a paid lesson on the instructor
     * Finances ledger. Booking fee and digital charge are excluded.
     * Idempotent per lesson_payment_id.
     */
    public function __invoke(LessonPayment $lessonPayment): ?InstructorFinance
    {
        $lessonPayment->loadMissing([
            'instructorFinance',
            'lesson.order.student',
            'lesson.instructor',
            'lesson.order.instructor',
        ]);

        if ($lessonPayment->status !== PaymentStatus::PAID) {
            return null;
        }

        if ($lessonPayment->instructorFinance) {
            return $lessonPayment->instructorFinance;
        }

        $lesson = $lessonPayment->lesson;
        $order = $lesson?->order;
        $instructor = $lesson?->instructor ?? $order?->instructor;

        if ($instructor === null) {
            Log::warning('Cannot record lesson fee on instructor profile: no instructor', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lesson?->id,
            ]);

            return null;
        }

        $lessonFeePence = $lessonPayment->lessonFeePence();

        if ($lessonFeePence <= 0) {
            Log::info('Skipping instructor lesson fee record: zero lesson fee', [
                'lesson_payment_id' => $lessonPayment->id,
            ]);

            return null;
        }

        $student = $order?->student;
        $studentName = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));
        $lessonDate = $lesson?->date?->format('d M Y') ?? 'unscheduled';

        $description = $studentName !== ''
            ? "Lesson fee — {$studentName} — {$lessonDate}"
            : "Lesson fee — {$lessonDate}";

        $attributes = [
            'type' => 'payment',
            'category' => 'lesson_fee',
            'payment_method' => 'card',
            'description' => $description,
            'amount_pence' => $lessonFeePence,
            'is_recurring' => false,
            'date' => ($lessonPayment->paid_at ?? now())->toDateString(),
            'notes' => 'Automatically recorded from lesson payment. Lesson fee only — excludes booking fee and digital charge.',
        ];

        try {
            $finance = $instructor->finances()->firstOrCreate(
                ['lesson_payment_id' => $lessonPayment->id],
                $attributes
            );
        } catch (QueryException $exception) {
            $finance = InstructorFinance::query()
                ->where('lesson_payment_id', $lessonPayment->id)
                ->first();

            if ($finance === null) {
                throw $exception;
            }
        }

        Log::info('Recorded lesson fee on instructor profile', [
            'instructor_finance_id' => $finance->id,
            'instructor_id' => $instructor->id,
            'lesson_payment_id' => $lessonPayment->id,
            'amount_pence' => $finance->amount_pence,
        ]);

        return $finance;
    }
}
