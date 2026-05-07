<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Profile;

use App\Enums\BusinessType;
use App\Models\Instructor;

class UpdateTaxProfileAction
{
    /**
     * @param  array{
     *     business_type: string,
     *     vat_registered: bool,
     *     vrn?: ?string,
     *     utr?: ?string,
     *     nino?: ?string,
     *     companies_house_number?: ?string,
     * }  $data
     */
    public function __invoke(Instructor $instructor, array $data): Instructor
    {
        $businessType = BusinessType::from($data['business_type']);
        $vatRegistered = (bool) $data['vat_registered'];

        $update = [
            'business_type' => $businessType,
            'vat_registered' => $vatRegistered,
            'vrn' => $vatRegistered ? ($data['vrn'] ?? null) : null,
            'utr' => $businessType === BusinessType::LimitedCompany
                ? null
                : ($data['utr'] ?? null),
            'nino' => $businessType === BusinessType::LimitedCompany
                ? null
                : ($data['nino'] ?? null),
            'companies_house_number' => $businessType === BusinessType::LimitedCompany
                ? ($data['companies_house_number'] ?? null)
                : null,
        ];

        if ($instructor->tax_profile_completed_at === null) {
            $update['tax_profile_completed_at'] = now();
        }

        $instructor->fill($update)->save();

        return $instructor->fresh();
    }
}
