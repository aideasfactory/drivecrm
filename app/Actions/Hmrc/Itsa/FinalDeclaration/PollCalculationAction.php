<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Enums\ItsaCalculationStatus;
use App\Models\HmrcItsaCalculation;
use App\Models\User;

/**
 * Loops `RetrieveCalculationAction` with backoff until the calculation is
 * either processed or errored, capped at ~60s. Returns the latest model
 * regardless of outcome — the controller is responsible for surfacing a
 * "still working" UI when the cap is hit while still pending.
 */
class PollCalculationAction
{
    public function __construct(
        private readonly RetrieveCalculationAction $retrieveCalculation,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        HmrcItsaCalculation $calculation,
        array $fraudContext = [],
        int $maxSeconds = 60,
    ): HmrcItsaCalculation {
        $delays = [1500, 3000, 6000, 12000, 30000];
        $deadline = (int) (microtime(true) * 1000) + ($maxSeconds * 1000);

        foreach ($delays as $i => $delayMs) {
            $calculation = ($this->retrieveCalculation)($user, $calculation, $fraudContext);

            if ($calculation->status !== ItsaCalculationStatus::Pending) {
                return $calculation;
            }

            if ((int) (microtime(true) * 1000) >= $deadline) {
                return $calculation;
            }

            $sleepMs = min($delayMs, $deadline - (int) (microtime(true) * 1000));
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $calculation;
    }
}
