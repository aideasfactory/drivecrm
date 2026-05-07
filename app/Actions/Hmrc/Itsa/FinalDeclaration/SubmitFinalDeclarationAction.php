<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaCalculationStatus;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaCalculation;
use App\Models\HmrcItsaFinalDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitFinalDeclarationAction
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
    ): HmrcItsaFinalDeclaration {
        if ($calculation->user_id !== $user->id) {
            throw new HmrcApiException(
                message: 'Calculation does not belong to the current user.',
                statusCode: 403,
            );
        }

        if ($calculation->status !== ItsaCalculationStatus::Processed) {
            throw new HmrcApiException(
                message: 'Calculation must be processed before final declaration can be submitted.',
                statusCode: 409,
            );
        }

        $existing = HmrcItsaFinalDeclaration::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $calculation->tax_year)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'POST',
            path: "/individuals/calculations/{$calculation->nino}/self-assessment/{$calculation->calculation_id}/final-declaration",
            version: '8.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        return DB::transaction(function () use ($user, $calculation, $response) {
            return HmrcItsaFinalDeclaration::create([
                'user_id' => $user->id,
                'nino' => $calculation->nino,
                'tax_year' => $calculation->tax_year,
                'calculation_id' => $calculation->id,
                'submitted_at' => now(),
                'correlation_id' => isset($response['_correlationId']) ? (string) $response['_correlationId'] : null,
                'request_payload' => ['calculationId' => $calculation->calculation_id],
                'response_payload' => $response,
                'digital_records_attested_at' => now(),
                'digital_records_attested_by_user_id' => $user->id,
            ]);
        });
    }
}
