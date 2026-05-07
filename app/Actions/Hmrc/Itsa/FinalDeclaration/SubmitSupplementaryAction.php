<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaSupplementaryType;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaSupplementaryData;
use App\Models\User;

class SubmitSupplementaryAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
        private readonly BuildSupplementaryPayloadAction $buildPayload,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated FormRequest output.
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        ItsaSupplementaryType $type,
        string $taxYear,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaSupplementaryData {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino) || $instructor->nino === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before submitting supplementary data.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        $payload = ($this->buildPayload)($type, $data);

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'PUT',
            path: $type->hmrcPath($nino, $taxYear),
            version: $type->hmrcVersion(),
            payload: $payload,
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        return HmrcItsaSupplementaryData::updateOrCreate(
            [
                'user_id' => $user->id,
                'tax_year' => $taxYear,
                'type' => $type->value,
            ],
            [
                'payload' => $payload,
                'submission_id' => isset($response['submissionId']) ? (string) $response['submissionId'] : null,
                'correlation_id' => isset($response['_correlationId']) ? (string) $response['_correlationId'] : null,
                'submitted_at' => now(),
                'response_payload' => $response,
            ],
        );
    }
}
