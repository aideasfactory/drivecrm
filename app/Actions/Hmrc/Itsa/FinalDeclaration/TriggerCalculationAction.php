<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaCalculationStatus;
use App\Enums\ItsaCalculationType;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaCalculation;
use App\Models\User;

class TriggerCalculationAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        string $taxYear,
        ItsaCalculationType $type,
        array $fraudContext = [],
    ): HmrcItsaCalculation {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino) || $instructor->nino === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before triggering a calculation.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        $query = ['taxYear' => $taxYear];
        if ($type->isFinal()) {
            $query['finalDeclaration'] = 'true';
        }

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'POST',
            path: "/individuals/calculations/{$nino}/self-assessment",
            version: '8.0',
            query: $query,
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        $calculationId = isset($response['calculationId']) ? (string) $response['calculationId'] : null;
        if ($calculationId === null || $calculationId === '') {
            throw new HmrcApiException(
                message: 'HMRC accepted the calculation request but returned no calculationId.',
                statusCode: 502,
            );
        }

        return HmrcItsaCalculation::updateOrCreate(
            [
                'user_id' => $user->id,
                'calculation_id' => $calculationId,
            ],
            [
                'nino' => $nino,
                'tax_year' => $taxYear,
                'calculation_type' => $type,
                'status' => ItsaCalculationStatus::Pending,
                'triggered_at' => now(),
                'processed_at' => null,
                'summary_payload' => null,
                'detail_payload' => null,
                'error_payload' => null,
            ],
        );
    }
}
