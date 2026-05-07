<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Profile;

use App\Enums\BusinessType;
use App\Models\Instructor;

class GetMtdApplicabilityAction
{
    /**
     * Resolve which MTD services apply to an instructor based on their tax profile.
     *
     * Returns an array shaped for the Inertia page; null business_type means the
     * profile hasn't been filled in yet, so nothing applies until they do so.
     *
     * @return array{
     *     profile_complete: bool,
     *     business_type: ?string,
     *     vat: array{applies: bool, vrn: ?string},
     *     itsa: array{applies: bool, status: string, thresholds: array<int, array{date: string, income: int, label: string}>},
     *     corporation_tax: array{applies: false, reason: string},
     *     summary: string,
     * }
     */
    public function __invoke(Instructor $instructor): array
    {
        $businessType = $instructor->business_type;
        $profileComplete = $instructor->tax_profile_completed_at !== null && $businessType !== null;

        $vatApplies = $profileComplete && $instructor->vat_registered === true;

        $itsaStatus = match ($businessType) {
            BusinessType::SoleTrader => 'mandated_by_threshold',
            BusinessType::Partnership => 'tbc_by_hmrc',
            BusinessType::LimitedCompany => 'not_applicable',
            null => 'unknown',
        };
        $itsaApplies = $businessType !== null && $businessType->itsaCanApply();

        return [
            'profile_complete' => $profileComplete,
            'business_type' => $businessType?->value,
            'vat' => [
                'applies' => $vatApplies,
                'vrn' => $vatApplies ? $instructor->vrn : null,
            ],
            'itsa' => [
                'applies' => $itsaApplies,
                'status' => $itsaStatus,
                'thresholds' => [
                    ['date' => '2026-04-06', 'income' => 50000, 'label' => 'Qualifying income above £50,000'],
                    ['date' => '2027-04-06', 'income' => 30000, 'label' => 'Qualifying income above £30,000'],
                    ['date' => '2028-04-06', 'income' => 20000, 'label' => 'Qualifying income above £20,000'],
                ],
            ],
            'corporation_tax' => [
                'applies' => false,
                'reason' => 'Corporation Tax for limited companies is not part of MTD and is not handled here.',
            ],
            'summary' => $this->summary($businessType, $vatApplies, $itsaApplies),
        ];
    }

    private function summary(?BusinessType $businessType, bool $vatApplies, bool $itsaApplies): string
    {
        if ($businessType === null) {
            return 'Complete your tax profile to see which HMRC services apply to you.';
        }

        if (! $vatApplies && ! $itsaApplies) {
            return 'Based on your tax profile, no MTD services currently apply to you.';
        }

        $parts = [];
        if ($itsaApplies) {
            $parts[] = $businessType === BusinessType::Partnership
                ? 'MTD Income Tax (timeline TBC by HMRC for partnerships)'
                : 'MTD Income Tax';
        }
        if ($vatApplies) {
            $parts[] = 'MTD VAT';
        }

        return 'Applicable services: '.implode(' and ', $parts).'.';
    }
}
