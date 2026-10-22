<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\PayoutStatus;
use App\Models\Instructor;
use Illuminate\Support\Collection;

/**
 * An instructor's lesson income for the Finances tab: paid Stripe payouts, and
 * cash / alternative-payment lessons they were paid for directly. Derived from
 * the same sources as the Reports tab, never stored in `instructor_finances`.
 */
class GetInstructorLessonIncomeAction
{
    public function __construct(
        protected GetInstructorPayoutsAction $getInstructorPayouts,
        protected GetInstructorCashLessonsAction $getInstructorCashLessons
    ) {}

    /**
     * @return Collection<int, array{key: string, source: 'payout'|'cash'|'alternative', description: string, package_name: string|null, date: string|null, amount_pence: int, formatted_amount: string}>
     */
    public function __invoke(Instructor $instructor): Collection
    {
        $payouts = ($this->getInstructorPayouts)($instructor)
            ->where('status', PayoutStatus::PAID->value)
            ->map(fn (array $payout): array => [
                'key' => 'payout-'.$payout['id'],
                'source' => 'payout',
                'description' => $this->describe($payout['student_name']),
                'package_name' => $payout['package_name'],
                'date' => $payout['lesson_date'],
                'amount_pence' => (int) $payout['amount_pence'],
                'formatted_amount' => $payout['formatted_amount'],
            ]);

        $directPayments = ($this->getInstructorCashLessons)($instructor)
            ->map(fn (array $lesson): array => [
                'key' => 'lesson-'.$lesson['lesson_id'],
                'source' => $lesson['is_alternative_payment'] ? 'alternative' : 'cash',
                'description' => $this->describe($lesson['student_name']),
                'package_name' => $lesson['package_name'],
                'date' => $lesson['lesson_date'],
                'amount_pence' => $lesson['amount_pence'],
                'formatted_amount' => $lesson['formatted_amount'],
            ]);

        return $payouts->concat($directPayments)
            ->sortByDesc('date')
            ->values();
    }

    protected function describe(?string $studentName): string
    {
        return $studentName ? "Lesson – {$studentName}" : 'Lesson';
    }
}
