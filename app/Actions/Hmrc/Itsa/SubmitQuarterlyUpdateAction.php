<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaExpenseCategory;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaObligation;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcItsaQuarterlyUpdateRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmitQuarterlyUpdateAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
        private readonly BuildQuarterlyPayloadAction $buildPayload,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated payload from SubmitQuarterlyUpdateRequest
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        string $businessId,
        string $periodKey,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaQuarterlyUpdate {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino)) {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before submitting an ITSA quarterly update.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        $payload = ($this->buildPayload)([
            'period_start_date' => $data['period_start_date'],
            'period_end_date' => $data['period_end_date'],
            'turnover_pence' => $data['turnover_pence'],
            'other_income_pence' => $data['other_income_pence'],
            'consolidated_expenses_pence' => $data['consolidated_expenses_pence'] ?? null,
            'expenses' => $data['expenses'] ?? [],
        ]);

        $row = $this->prepareRow($user, $businessId, $periodKey, $data);

        try {
            $response = ($this->callHmrcApi)(
                user: $user,
                method: 'POST',
                path: "/individuals/business/self-employment/{$nino}/{$businessId}/period",
                version: '5.0',
                payload: $payload,
                withFraudHeaders: true,
                fraudContext: $fraudContext,
            );
        } catch (HmrcApiException|Throwable $exception) {
            $this->writeFailedRevision($user, $row, $payload, $exception);
            throw $exception;
        }

        return $this->writeSuccess($user, $row, $payload, $response, $data, kind: 'submission');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function prepareRow(User $user, string $businessId, string $periodKey, array $data): HmrcItsaQuarterlyUpdate
    {
        $instructor = $user->instructor;

        $columns = [
            'instructor_id' => $instructor?->id,
            'period_start_date' => $data['period_start_date'],
            'period_end_date' => $data['period_end_date'],
            'turnover_pence' => (int) $data['turnover_pence'],
            'other_income_pence' => (int) $data['other_income_pence'],
            'consolidated_expenses_pence' => $data['consolidated_expenses_pence'] ?? null,
            'digital_records_attested_at' => now(),
            'digital_records_attested_by_user_id' => $user->id,
        ];

        $expenses = is_array($data['expenses'] ?? null) ? $data['expenses'] : [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $columns[$category->column()] = $expenses[$category->value] ?? null;
        }

        return HmrcItsaQuarterlyUpdate::firstOrNew(
            [
                'user_id' => $user->id,
                'business_id' => $businessId,
                'period_key' => $periodKey,
            ],
            $columns,
        )->fill($columns);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $data
     */
    private function writeSuccess(
        User $user,
        HmrcItsaQuarterlyUpdate $row,
        array $payload,
        array $response,
        array $data,
        string $kind,
    ): HmrcItsaQuarterlyUpdate {
        return DB::transaction(function () use ($user, $row, $payload, $response, $kind) {
            $submissionId = isset($response['submissionId'])
                ? (string) $response['submissionId']
                : (isset($response['periodId']) ? (string) $response['periodId'] : null);
            $correlationId = isset($response['_correlationId']) ? (string) $response['_correlationId'] : null;

            $row->forceFill([
                'submission_id' => $submissionId,
                'correlation_id' => $correlationId,
                'submitted_at' => now(),
                'request_payload' => $payload,
                'response_payload' => $response,
            ])->save();

            HmrcItsaQuarterlyUpdateRevision::create([
                'quarterly_update_id' => $row->id,
                'user_id' => $user->id,
                'revision_number' => $row->nextRevisionNumber(),
                'kind' => $kind,
                'request_payload' => $payload,
                'response_payload' => $response,
                'submission_id' => $submissionId,
                'correlation_id' => $correlationId,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->id,
                'digital_records_attested_at' => now(),
            ]);

            HmrcItsaObligation::query()
                ->where('user_id', $user->id)
                ->where('business_id', $row->business_id)
                ->where('period_key', $row->period_key)
                ->update([
                    'status' => 'Fulfilled',
                    'received_date' => now()->toDateString(),
                    'last_synced_at' => now(),
                ]);

            return $row->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeFailedRevision(User $user, HmrcItsaQuarterlyUpdate $row, array $payload, Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($user, $row, $payload, $exception) {
                if (! $row->exists) {
                    $row->save();
                }
                $response = $this->failureEnvelope($exception);

                HmrcItsaQuarterlyUpdateRevision::create([
                    'quarterly_update_id' => $row->id,
                    'user_id' => $user->id,
                    'revision_number' => $row->nextRevisionNumber(),
                    'kind' => 'failed_submission',
                    'request_payload' => $payload,
                    'response_payload' => $response,
                    'submission_id' => null,
                    'correlation_id' => null,
                    'submitted_at' => now(),
                    'submitted_by_user_id' => $user->id,
                    'digital_records_attested_at' => $row->digital_records_attested_at,
                ]);
            });
        } catch (Throwable) {
            // Audit-trail write failure must not mask the original HMRC exception.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function failureEnvelope(Throwable $exception): array
    {
        if ($exception instanceof HmrcApiException) {
            return [
                'status' => $exception->statusCode,
                'code' => $exception->hmrcCode,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ];
        }

        return [
            'status' => 0,
            'code' => 'NETWORK_ERROR',
            'message' => $exception->getMessage(),
        ];
    }
}
