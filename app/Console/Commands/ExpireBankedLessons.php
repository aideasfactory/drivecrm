<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Calendar\CancelBookingAction;
use App\Enums\LessonCancellationReason;
use App\Models\Lesson;
use App\Support\BookingPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels banked lessons (kept in their booking to rebook later) that still
 * haven't been rebooked `booking_payments.banked_lesson_expiry_months` after
 * they were banked. Each goes through the normal cancel: the pupil is
 * emailed and a paid lesson is sent to Head Office to refund.
 */
class ExpireBankedLessons extends Command
{
    protected $signature = 'lessons:expire-banked';

    protected $description = 'Cancel and refund banked lessons not rebooked within the expiry period';

    public function handle(CancelBookingAction $cancelBooking): int
    {
        $months = BookingPayments::bankedLessonExpiryMonths();

        $lessons = Lesson::query()
            ->banked()
            ->where('banked_at', '<=', now()->subMonthsNoOverflow($months))
            ->orderBy('banked_at')
            ->get();

        if ($lessons->isEmpty()) {
            $this->info('No banked lessons have expired.');

            return self::SUCCESS;
        }

        $counts = ['cancelled' => 0, 'refund_review' => 0, 'failed' => 0];

        foreach ($lessons as $lesson) {
            try {
                $result = $cancelBooking->cancelBankedLesson(
                    $lesson,
                    LessonCancellationReason::BankedExpired,
                    "Kept to rebook later on {$lesson->banked_at->format('j M Y')} and not rebooked within {$months} months",
                    null,
                );

                $counts['cancelled']++;
                $counts['refund_review'] += $result['refund_required_count'];
                $this->line("Lesson #{$lesson->id}: cancelled".($result['refund_required_count'] > 0 ? ' (Head Office to refund)' : ''));
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->error("Lesson #{$lesson->id}: {$e->getMessage()}");

                Log::error('Failed to expire banked lesson', [
                    'lesson_id' => $lesson->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Cancelled {$counts['cancelled']} expired banked lesson(s), {$counts['refund_review']} sent to Head Office to refund, {$counts['failed']} failed.");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
