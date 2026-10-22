<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\FinanceSource;
use App\Enums\LessonStatus;
use App\Models\InstructorFinance;
use App\Models\Lesson;

class SyncCashLessonFinanceEntryAction
{
    public function __construct(
        protected UpsertAutomaticFinanceEntryAction $upsertAutomaticFinanceEntry,
    ) {}

    /**
     * Mirror a signed-off cash lesson, or a lesson signed off as paid by an
     * alternative method, into the instructor's finances as income — the same
     * lessons GetInstructorCashLessonsAction lists in the Reports tab. The
     * pupil paid the instructor directly, so there is no payout. A lesson
     * with a payout is already counted through the payout and is skipped.
     * Any lesson that does not qualify has its automatic row removed.
     */
    public function __invoke(Lesson $lesson): ?InstructorFinance
    {
        if (! $lesson->exists || $lesson->status !== LessonStatus::COMPLETED || $lesson->instructor_id === null) {
            return $this->remove($lesson);
        }

        $lesson->load(['order.student.user', 'lessonPayment']);

        $isCash = $lesson->order?->isCash() === true;
        $isAlternativePayment = $lesson->lessonPayment?->isPaidByAlternativeMethod() === true;

        if ((! $isCash && ! $isAlternativePayment) || $lesson->payout()->exists()) {
            return $this->remove($lesson);
        }

        $amountPence = (int) ($lesson->lessonPayment?->amount_pence ?? $lesson->amount_pence);

        if ($amountPence <= 0) {
            return $this->remove($lesson);
        }

        $student = $lesson->order?->student;
        $studentName = $student
            ? (trim(($student->first_name ?? '').' '.($student->surname ?? '')) ?: ($student->user?->name ?? null))
            : null;
        $lessonDate = $lesson->date?->toDateString();
        $receivedOn = ($lesson->completed_at ?? $lesson->date ?? now())->toDateString();

        $description = implode(' — ', array_filter([
            $isCash ? 'Cash lesson' : 'Lesson paid by alternative method',
            $studentName,
            $lessonDate ? 'lesson '.$lesson->date->format('d M Y') : null,
        ]));

        $notes = implode("\n", array_filter([
            $lesson->order?->package_name ? "Package: {$lesson->order->package_name}" : null,
            "Lesson #{$lesson->id}",
        ]));

        return ($this->upsertAutomaticFinanceEntry)(
            (int) $lesson->instructor_id,
            FinanceSource::CashLesson,
            (int) $lesson->id,
            [
                'category' => $isCash ? 'cash_lesson' : 'alternative_payment_lesson',
                'payment_method' => $isCash ? 'cash' : null,
                'description' => mb_substr($description, 0, 255),
                'amount_pence' => $amountPence,
                'date' => $receivedOn,
                'notes' => mb_substr($notes, 0, 1000),
            ],
            [$receivedOn, $lessonDate],
            $isCash ? [null, 'cash'] : [],
        );
    }

    private function remove(Lesson $lesson): null
    {
        return ($this->upsertAutomaticFinanceEntry)((int) $lesson->instructor_id, FinanceSource::CashLesson, (int) $lesson->id, null);
    }
}
