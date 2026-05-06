<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supplementary data submission types that feed the Final Declaration. Each
 * case knows its HMRC API path, version, and human label so the controller
 * + service stay free of magic strings. Endpoint paths exclude the API base
 * — `CallHmrcApiAction` joins them.
 */
enum ItsaSupplementaryType: string
{
    case Reliefs = 'reliefs';
    case Disclosures = 'disclosures';
    case Savings = 'savings';
    case Dividends = 'dividends';
    case IndividualDetails = 'individual_details';

    public function label(): string
    {
        return match ($this) {
            self::Reliefs => 'Reliefs (pension & charity)',
            self::Disclosures => 'Disclosures (Marriage Allowance)',
            self::Savings => 'Savings income',
            self::Dividends => 'Dividends income',
            self::IndividualDetails => 'Personal details',
        };
    }

    public function hmrcVersion(): string
    {
        return match ($this) {
            self::Reliefs => '3.0',
            self::Disclosures, self::Savings, self::Dividends, self::IndividualDetails => '2.0',
        };
    }

    public function hmrcPath(string $nino, string $taxYear): string
    {
        return match ($this) {
            self::Reliefs => "/individuals/reliefs/{$nino}/{$taxYear}",
            self::Disclosures => "/individuals/disclosures/{$nino}/{$taxYear}",
            self::Savings => "/individuals/savings-income/{$nino}/{$taxYear}",
            self::Dividends => "/individuals/dividends-income/{$nino}/{$taxYear}",
            self::IndividualDetails => "/individuals/self-assessment/individuals/details/{$nino}/{$taxYear}",
        };
    }

    /**
     * Fields required by Phase 3.5 v1 form scope. The wider HMRC schema is
     * a superset; we only collect/validate the subset relevant to driving
     * instructors and persist the JSON on `hmrc_itsa_supplementary_data.payload`.
     *
     * @return array<int, string>
     */
    public function v1Fields(): array
    {
        return match ($this) {
            self::Reliefs => [
                'pension_contributions_pence',
                'one_off_pension_contributions_pence',
                'charitable_giving_pence',
            ],
            self::Disclosures => [
                'marriage_allowance_recipient_nino',
                'marriage_allowance_start_date',
            ],
            self::Savings => [
                'uk_interest_pence',
                'foreign_interest_pence',
            ],
            self::Dividends => [
                'uk_dividends_pence',
                'other_uk_dividends_pence',
            ],
            self::IndividualDetails => [
                'first_name',
                'last_name',
                'address_line_1',
                'address_line_2',
                'postcode',
                'marital_status',
            ],
        };
    }
}
