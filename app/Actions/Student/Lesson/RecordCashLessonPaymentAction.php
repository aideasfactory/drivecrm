<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;

class RecordCashLessonPaymentAction
{
    /**
     * Record a cash lesson as paid when it is signed off: the pupil paid the
     * instructor the lesson price in cash, with no fees. Cash lessons have no
     * payment row until now, so nothing ever chases or invoices them. The row
     * makes the lesson show as a payment in the pupil's payments and revenue.
     * Safe to call twice — an existing row is left as it is.
     */
    public function __invoke(Lesson $lesson): LessonPayment
    {
        return LessonPayment::query()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'amount_pence' => (int) $lesson->amount_pence,
                'test_pass_guarantee_pence' => 0,
                'status' => PaymentStatus::PAID,
                'due_date' => $lesson->date?->toDateString(),
                'paid_at' => now(),
            ],
        );
    }
}
