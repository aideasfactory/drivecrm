<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Enums\ItsaSupplementaryType;
use App\Support\HmrcMoney;

/**
 * Translates the v1 supplementary form fields into the HMRC JSON shape per
 * type. Mirrors the approach used by `BuildQuarterlyPayloadAction`: a single
 * action keeps payload assembly out of the HTTP layer and out of the
 * generic Submit action.
 */
class BuildSupplementaryPayloadAction
{
    /**
     * @param  array<string, mixed>  $data  Validated FormRequest output (pence integers + raw strings).
     * @return array<string, mixed>
     */
    public function __invoke(ItsaSupplementaryType $type, array $data): array
    {
        return match ($type) {
            ItsaSupplementaryType::Reliefs => $this->reliefs($data),
            ItsaSupplementaryType::Disclosures => $this->disclosures($data),
            ItsaSupplementaryType::Savings => $this->savings($data),
            ItsaSupplementaryType::Dividends => $this->dividends($data),
            ItsaSupplementaryType::IndividualDetails => $this->individualDetails($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reliefs(array $data): array
    {
        $payload = [];

        $regular = $this->pounds($data['pension_contributions_pence'] ?? null);
        $oneOff = $this->pounds($data['one_off_pension_contributions_pence'] ?? null);
        if ($regular !== null || $oneOff !== null) {
            $payload['pensionReliefs'] = array_filter([
                'regularPensionContributions' => $regular,
                'oneOffPensionContributionsPaid' => $oneOff,
            ], fn ($v) => $v !== null);
        }

        $charity = $this->pounds($data['charitable_giving_pence'] ?? null);
        if ($charity !== null) {
            $payload['charitableGivingTaxRelief'] = [
                'nonUkCharities' => ['totalAmount' => $charity],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function disclosures(array $data): array
    {
        $recipientNino = $data['marriage_allowance_recipient_nino'] ?? null;
        $startDate = $data['marriage_allowance_start_date'] ?? null;

        if (! is_string($recipientNino) || $recipientNino === '') {
            return [];
        }

        return [
            'marriageAllowance' => array_filter([
                'spouseOrCivilPartnerNino' => $recipientNino,
                'startDate' => is_string($startDate) && $startDate !== '' ? $startDate : null,
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function savings(array $data): array
    {
        $payload = [];

        $uk = $this->pounds($data['uk_interest_pence'] ?? null);
        if ($uk !== null) {
            $payload['ukSavings'] = ['untaxedUkInterest' => $uk];
        }

        $foreign = $this->pounds($data['foreign_interest_pence'] ?? null);
        if ($foreign !== null) {
            $payload['foreignSavings'] = ['totalAmount' => $foreign];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dividends(array $data): array
    {
        $payload = [];

        $uk = $this->pounds($data['uk_dividends_pence'] ?? null);
        if ($uk !== null) {
            $payload['ukDividends'] = ['totalAmount' => $uk];
        }

        $other = $this->pounds($data['other_uk_dividends_pence'] ?? null);
        if ($other !== null) {
            $payload['otherUkDividends'] = ['totalAmount' => $other];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function individualDetails(array $data): array
    {
        return array_filter([
            'firstName' => $data['first_name'] ?? null,
            'lastName' => $data['last_name'] ?? null,
            'address' => array_filter([
                'addressLine1' => $data['address_line_1'] ?? null,
                'addressLine2' => $data['address_line_2'] ?? null,
                'postcode' => $data['postcode'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'maritalStatus' => $data['marital_status'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function pounds(mixed $pence): ?float
    {
        if ($pence === null || $pence === '') {
            return null;
        }

        return HmrcMoney::toHmrcPayload((int) $pence, allowNegative: false);
    }
}
