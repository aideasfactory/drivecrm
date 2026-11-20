<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonStatus;
use App\Models\Lesson;

class GetLessonDeletionBlockerAction
{
    /**
     * Why a lesson can't be permanently deleted at an instructor's request, or
     * null when it can. Checked when the instructor asks and again when an
     * owner approves, since the lesson may have been paid or signed off since.
     *
     * Only a past (started), open, un-signed-off lesson with no money tied to
     * it qualifies: no instructor payout in any state, no payment taken or
     * refunded, no open Stripe invoice the pupil could still pay, not part of
     * an upfront booking (paid for as a whole), not imported (settled outside
     * the platform) and no income recorded against it in the instructor's
     * finances. A cash lesson qualifies because its payment is only recorded
     * at sign-off.
     */
    public function __invoke(?Lesson $lesson): ?string
    {
        if ($lesson === null) {
            return 'This lesson no longer exists.';
        }

        $lesson->loadMissing(['order', 'lessonPayment', 'payout']);

        if ($lesson->status === LessonStatus::CANCELLED) {
            return 'This lesson has been cancelled, so there is nothing to delete.';
        }

        if ($lesson->status === LessonStatus::COMPLETED || $lesson->completed_at !== null) {
            return "This lesson has been signed off, so it can't be deleted.";
        }

        if ($lesson->status === LessonStatus::DRAFT) {
            return "This lesson's booking hasn't been confirmed, so it can't be deleted.";
        }

        if ($lesson->isBanked() || $lesson->date === null) {
            return "This lesson is waiting to be rebooked, so it can't be deleted.";
        }

        if (! $lesson->hasStarted()) {
            return "This lesson hasn't happened yet. Cancel it from the diary instead.";
        }

        if ($lesson->payout !== null) {
            return "This lesson has an instructor payout, so it can't be deleted.";
        }

        $payment = $lesson->lessonPayment;

        if ($payment?->isPaid()) {
            return "This lesson has been paid for, so it can't be deleted.";
        }

        if ($payment?->isRefunded()) {
            return "This lesson has a refunded payment, so it can't be deleted.";
        }

        if ($payment?->isDue() && $payment->stripe_invoice_id !== null && $payment->invoice_cancelled_at === null) {
            return "This lesson has an open invoice the pupil could still pay, so it can't be deleted until the invoice is cancelled.";
        }

        if ($lesson->order?->isUpfront()) {
            return "This lesson was paid for upfront as part of its booking, so it can't be deleted.";
        }

        if ($lesson->order?->isImported()) {
            return "This lesson was imported as paid from the previous system, so it can't be deleted.";
        }

        $hasFinanceRecord = $lesson->relationLoaded('instructorFinances')
            ? $lesson->instructorFinances->isNotEmpty()
            : $lesson->instructorFinances()->exists();

        if ($hasFinanceRecord) {
            return "This lesson has a payment recorded in the instructor's finances, so it can't be deleted.";
        }

        return null;
    }
}
