<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaCalculationStatus;
use App\Models\HmrcItsaCalculation;
use App\Models\User;

class RetrieveCalculationAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        HmrcItsaCalculation $calculation,
        array $fraudContext = [],
    ): HmrcItsaCalculation {
        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/individuals/calculations/{$calculation->nino}/self-assessment/{$calculation->calculation_id}",
            version: '8.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        $outcome = $this->extractOutcome($response);
        $status = ItsaCalculationStatus::fromHmrcOutcome($outcome);

        $update = [
            'status' => $status,
            'detail_payload' => $response,
            'summary_payload' => $this->extractSummary($response),
        ];

        if ($status === ItsaCalculationStatus::Processed && $calculation->processed_at === null) {
            $update['processed_at'] = now();
        }

        if ($status === ItsaCalculationStatus::Errored) {
            $update['error_payload'] = is_array($response['errors'] ?? null) ? $response['errors'] : $response;
        }

        $calculation->fill($update)->save();

        return $calculation->fresh() ?? $calculation;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractOutcome(array $response): ?string
    {
        $metadata = $response['metadata'] ?? null;
        if (! is_array($metadata)) {
            return null;
        }

        $outcome = $metadata['calculationOutcome'] ?? null;

        return is_string($outcome) ? $outcome : null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function extractSummary(array $response): ?array
    {
        $liability = $response['liabilityAndCalculation'] ?? $response['calculation'] ?? null;

        return is_array($liability) ? $liability : null;
    }
}
