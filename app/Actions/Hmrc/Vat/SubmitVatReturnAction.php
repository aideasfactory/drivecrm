<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Vat;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaObligationStatus;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcVatObligation;
use App\Models\HmrcVatReturn;
use App\Models\User;

class SubmitVatReturnAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
        private readonly BuildVatReturnPayloadAction $buildPayload,
    ) {}

    /**
     * Submit a 9-box VAT return to HMRC. Idempotent on `(user_id, vrn, period_key)`:
     * if a row already exists for the period, returns it without re-submitting.
     *
     * VAT submissions cannot be amended at HMRC, so the local row is the authoritative
     * record and corrections are made via a future-period adjustment, not by editing.
     *
     * @param  array<string, mixed>  $data  Validated payload from SubmitVatReturnRequest
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(User $user, string $periodKey, array $data, array $fraudContext = []): HmrcVatReturn
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->vrn) || $instructor->vrn === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a VAT registration number (VRN) before submitting a VAT return.',
                statusCode: 400,
            );
        }
        $vrn = $instructor->vrn;

        $existing = HmrcVatReturn::query()
            ->where('user_id', $user->id)
            ->where('vrn', $vrn)
            ->where('period_key', $periodKey)
            ->first();

        if ($existing !== null && $existing->submitted_at !== null) {
            return $existing;
        }

        $payload = ($this->buildPayload)(array_merge($data, ['period_key' => $periodKey]));

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'POST',
            path: "/organisations/vat/{$vrn}/returns",
            version: '1.0',
            payload: $payload,
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        $row = HmrcVatReturn::updateOrCreate(
            [
                'user_id' => $user->id,
                'vrn' => $vrn,
                'period_key' => $periodKey,
            ],
            [
                'instructor_id' => $instructor->id,
                'vat_due_sales_pence' => (int) $data['vat_due_sales_pence'],
                'vat_due_acquisitions_pence' => (int) $data['vat_due_acquisitions_pence'],
                'total_vat_due_pence' => (int) $data['total_vat_due_pence'],
                'vat_reclaimed_curr_period_pence' => (int) $data['vat_reclaimed_curr_period_pence'],
                'net_vat_due_pence' => (int) $data['net_vat_due_pence'],
                'total_value_sales_ex_vat_pence' => (int) $data['total_value_sales_ex_vat_pence'],
                'total_value_purchases_ex_vat_pence' => (int) $data['total_value_purchases_ex_vat_pence'],
                'total_value_goods_supplied_ex_vat_pence' => (int) $data['total_value_goods_supplied_ex_vat_pence'],
                'total_acquisitions_ex_vat_pence' => (int) $data['total_acquisitions_ex_vat_pence'],
                'finalised' => true,
                'submitted_at' => now(),
                'processing_date' => $response['processingDate'] ?? null,
                'form_bundle_number' => isset($response['formBundleNumber']) ? (string) $response['formBundleNumber'] : null,
                'charge_ref_number' => isset($response['chargeRefNumber']) ? (string) $response['chargeRefNumber'] : null,
                'payment_indicator' => isset($response['paymentIndicator']) ? (string) $response['paymentIndicator'] : null,
                'correlation_id' => isset($response['_correlationId']) ? (string) $response['_correlationId'] : null,
                'request_payload' => $payload,
                'response_payload' => $response,
                'digital_records_attested_at' => now(),
                'digital_records_attested_by_user_id' => $user->id,
            ],
        );

        HmrcVatObligation::query()
            ->where('user_id', $user->id)
            ->where('vrn', $vrn)
            ->where('period_key', $periodKey)
            ->update([
                'status' => ItsaObligationStatus::Fulfilled->value,
                'received_date' => now()->toDateString(),
                'last_synced_at' => now(),
            ]);

        return $row->fresh();
    }
}
