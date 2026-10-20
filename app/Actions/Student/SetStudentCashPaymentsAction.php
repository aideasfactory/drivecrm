<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Models\User;

class SetStudentCashPaymentsAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Turn cash (alternative) payments on or off for a pupil. Off by default
     * (the importer never turns it on). Existing orders are left untouched.
     *
     * Turning it off puts unpaid lessons back in the normal card flow: any
     * still-due payment an instructor had confirmed as paid by an alternative
     * method loses that confirmation, so it is invoiced and chased as usual
     * and can't get stuck unpaid and unsignable.
     */
    public function __invoke(Student $student, bool $enabled, ?User $changedBy = null): Student
    {
        if ($student->cash_payments_enabled === $enabled) {
            return $student;
        }

        $student->forceFill(['cash_payments_enabled' => $enabled])->save();

        $clearedConfirmations = $enabled ? 0 : $this->clearUnpaidAlternativeConfirmations($student);

        $changedByName = $changedBy?->name ?? 'head office';

        ($this->logActivity)(
            $student,
            $enabled
                ? "Alternative payments (e.g. cash) turned on by {$changedByName}"
                : "Alternative payments (e.g. cash) turned off by {$changedByName}",
            'profile',
            [
                'cash_payments_enabled' => $enabled,
                'changed_by_user_id' => $changedBy?->id,
                'unpaid_alternative_confirmations_cleared' => $clearedConfirmations,
            ],
        );

        return $student->refresh();
    }

    /**
     * Clear the alternative-payment confirmation on the pupil's lesson
     * payments that are still due. Paid ones keep it as their record.
     */
    protected function clearUnpaidAlternativeConfirmations(Student $student): int
    {
        return LessonPayment::query()
            ->where('status', PaymentStatus::DUE)
            ->whereNotNull('alternative_payment_confirmed_at')
            ->whereHas('lesson.order', fn ($query) => $query->where('student_id', $student->id))
            ->update([
                'alternative_payment_confirmed_at' => null,
                'alternative_payment_confirmed_by' => null,
            ]);
    }
}
