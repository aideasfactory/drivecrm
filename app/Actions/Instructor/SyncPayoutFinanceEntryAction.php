<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\FinanceSource;
use App\Models\InstructorFinance;
use App\Models\Payout;

class SyncPayoutFinanceEntryAction
{
    public function __construct(
        protected UpsertAutomaticFinanceEntryAction $upsertAutomaticFinanceEntry,
    ) {}

    /**
     * Mirror a lesson payout into the instructor's finances as income. A paid
     * payout (Stripe transfer, or paid by staff by hand after a failed
     * transfer) gets one read-only payment row dated on the day it was paid;
     * any other state removes it. Reads the payout only — never touches it.
     */
    public function __invoke(Payout $payout): ?InstructorFinance
    {
        $isIncome = $payout->exists
            && $payout->isCompleted()
            && (int) $payout->amount_pence > 0
            && $payout->instructor_id !== null;

        if (! $isIncome) {
            return ($this->upsertAutomaticFinanceEntry)((int) $payout->instructor_id, FinanceSource::StripePayout, (int) $payout->id, null);
        }

        $payout->loadMissing('lesson.order.student.user');

        $lesson = $payout->lesson;
        $order = $lesson?->order;
        $student = $order?->student;
        $paidOn = ($payout->paid_at ?? $payout->updated_at ?? now())->toDateString();
        $lessonDate = $lesson?->date?->toDateString();

        $studentName = null;
        if ($student) {
            $studentName = trim(($student->first_name ?? '').' '.($student->surname ?? '')) ?: ($student->user?->name ?? null);
        }

        $paidManually = $payout->wasPaidManually();

        $description = implode(' — ', array_filter([
            $paidManually ? 'Lesson payout (paid manually)' : 'Lesson payout (Stripe)',
            $studentName,
            $lessonDate ? 'lesson '.$lesson->date->format('d M Y') : null,
        ]));

        $notes = implode("\n", array_filter([
            $order?->package_name ? "Package: {$order->package_name}" : null,
            $payout->stripe_transfer_id ? "Stripe transfer: {$payout->stripe_transfer_id}" : null,
            $payout->manual_payment_reference ? "Manual payment reference: {$payout->manual_payment_reference}" : null,
            "Payout #{$payout->id}",
        ]));

        return ($this->upsertAutomaticFinanceEntry)(
            (int) $payout->instructor_id,
            FinanceSource::StripePayout,
            (int) $payout->id,
            [
                'category' => 'lesson_payout',
                'payment_method' => 'bank_transfer',
                'description' => mb_substr($description, 0, 255),
                'amount_pence' => (int) $payout->amount_pence,
                'date' => $paidOn,
                'notes' => mb_substr($notes, 0, 1000),
            ],
            [$paidOn, $lessonDate],
            [null, 'bank_transfer', 'card'],
        );
    }
}
