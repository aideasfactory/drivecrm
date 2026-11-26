<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Payment\GetLabellablePayoutsAction;
use App\Actions\Payment\LabelInstructorPayoutAction;
use App\Models\Payout;
use App\Support\PayoutDescriptions;
use Carbon\CarbonImmutable;

/**
 * Labels instructor payouts in their Stripe accounts so they can tell which
 * learner and lesson each payment is for. Text only: never moves money.
 */
class PayoutLabelService extends BaseService
{
    /**
     * Pause between Stripe calls when labelling past payouts, to stay well
     * under Stripe's rate limits.
     */
    protected const BACKFILL_PAUSE_MICROSECONDS = 150_000;

    public function __construct(
        protected LabelInstructorPayoutAction $labelInstructorPayout,
        protected GetLabellablePayoutsAction $getLabellablePayouts,
    ) {}

    /**
     * Label one payout. Never throws.
     */
    public function label(Payout $payout, ?string $destinationPaymentId = null): bool
    {
        return ($this->labelInstructorPayout)($payout, $destinationPaymentId);
    }

    /**
     * Label past payouts. With $dryRun, only lists what would be written.
     *
     * @return list<array{payout_id: int, instructor: ?string, description: string, labelled: ?bool}>
     */
    public function labelPastPayouts(bool $dryRun, ?int $instructorId = null, ?CarbonImmutable $since = null): array
    {
        $results = [];

        foreach (($this->getLabellablePayouts)($instructorId, $since) as $payout) {
            $description = $payout->lesson !== null
                ? PayoutDescriptions::forLesson($payout->lesson)['description']
                : '(lesson missing)';

            $labelled = null;

            if (! $dryRun) {
                $labelled = $this->label($payout);
                usleep(self::BACKFILL_PAUSE_MICROSECONDS);
            }

            $results[] = [
                'payout_id' => $payout->id,
                'instructor' => $payout->instructor?->user?->name,
                'description' => $description,
                'labelled' => $labelled,
            ];
        }

        return $results;
    }
}
