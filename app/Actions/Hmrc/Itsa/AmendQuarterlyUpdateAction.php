<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaExpenseCategory;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcItsaQuarterlyUpdateRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class AmendQuarterlyUpdateAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
        private readonly BuildQuarterlyPayloadAction $buildPayload,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated payload from AmendQuarterlyUpdateRequest
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(
        User $user,
        HmrcItsaQuarterlyUpdate $row,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaQuarterlyUpdate {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino)) {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before amending an ITSA quarterly update.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;
        $periodId = (string) ($row->submission_id ?? '');
        if ($periodId === '') {
            throw new HmrcApiException(
                message: 'No HMRC submission id is on file for this period — submit it first.',
                statusCode: 400,
            );
        }

        $payload = ($this->buildPayload)([
            'period_start_date' => $row->period_start_date->toDateString(),
            'period_end_date' => $row->period_end_date->toDateString(),
            'turnover_pence' => (int) $data['turnover_pence'],
            'other_income_pence' => (int) $data['other_income_pence'],
            'consolidated_expenses_pence' => $data['consolidated_expenses_pence'] ?? null,
            'expenses' => $data['expenses'] ?? [],
        ]);

        try {
            $response = ($this->callHmrcApi)(
                user: $user,
                method: 'PUT',
                path: "/individuals/business/self-employment/{$nino}/{$row->business_id}/period/{$periodId}",
                version: '5.0',
                payload: $payload,
                withFraudHeaders: true,
                fraudContext: $fraudContext,
            );
        } catch (Throwable $exception) {
            $this->writeFailedRevision($user, $row, $payload, $exception);
            throw $exception;
        }

        return DB::transaction(function () use ($user, $row, $payload, $response, $data) {
            $columns = [
                'turnover_pence' => (int) $data['turnover_pence'],
                'other_income_pence' => (int) $data['other_income_pence'],
                'consolidated_expenses_pence' => $data['consolidated_expenses_pence'] ?? null,
                'submitted_at' => now(),
                'request_payload' => $payload,
                'response_payload' => $response,
                'digital_records_attested_at' => now(),
                'digital_records_attested_by_user_id' => $user->id,
            ];
            $expenses = is_array($data['expenses'] ?? null) ? $data['expenses'] : [];
            foreach (ItsaExpenseCategory::cases() as $category) {
                $columns[$category->column()] = $expenses[$category->value] ?? null;
            }

            $row->forceFill($columns)->save();

            HmrcItsaQuarterlyUpdateRevision::create([
                'quarterly_update_id' => $row->id,
                'user_id' => $user->id,
                'revision_number' => $row->nextRevisionNumber(),
                'kind' => 'amendment',
                'request_payload' => $payload,
                'response_payload' => $response,
                'submission_id' => $row->submission_id,
                'correlation_id' => isset($response['_correlationId']) ? (string) $response['_correlationId'] : null,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->id,
                'digital_records_attested_at' => now(),
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
            $response = $exception instanceof HmrcApiException
                ? [
                    'status' => $exception->statusCode,
                    'code' => $exception->hmrcCode,
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors,
                ]
                : ['status' => 0, 'code' => 'NETWORK_ERROR', 'message' => $exception->getMessage()];

            HmrcItsaQuarterlyUpdateRevision::create([
                'quarterly_update_id' => $row->id,
                'user_id' => $user->id,
                'revision_number' => $row->nextRevisionNumber(),
                'kind' => 'failed_amendment',
                'request_payload' => $payload,
                'response_payload' => $response,
                'submission_id' => $row->submission_id,
                'correlation_id' => null,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->id,
                'digital_records_attested_at' => $row->digital_records_attested_at,
            ]);
        } catch (Throwable) {
            // Audit-trail write failure must not mask the original exception.
        }
    }
}
