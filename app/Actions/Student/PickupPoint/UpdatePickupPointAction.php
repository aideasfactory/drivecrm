<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Actions\Shared\LookupPostcodeAction;
use App\Models\StudentPickupPoint;

class UpdatePickupPointAction
{
    public function __construct(
        protected LookupPostcodeAction $lookupPostcode
    ) {}

    /**
     * Update an existing pickup point, re-geocoding if postcode changed.
     *
     * @param  array{label: string, address: string, postcode: string, is_default?: bool}  $data
     */
    public function __invoke(StudentPickupPoint $pickupPoint, array $data): StudentPickupPoint
    {
        // If setting as default, unset any existing defaults
        if (! empty($data['is_default'])) {
            $pickupPoint->student->pickupPoints()
                ->where('id', '!=', $pickupPoint->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $updateData = [
            'label' => $data['label'],
            'address' => $data['address'],
            'postcode' => strtoupper(trim($data['postcode'])),
            'is_default' => $data['is_default'] ?? false,
        ];

        // Re-geocode if postcode changed
        $normalizedNew = strtoupper(str_replace(' ', '', trim($data['postcode'])));
        $normalizedOld = strtoupper(str_replace(' ', '', $pickupPoint->postcode));

        if ($normalizedNew !== $normalizedOld) {
            $coordinates = ($this->lookupPostcode)($data['postcode']);
            $updateData['latitude'] = $coordinates['latitude'] ?? null;
            $updateData['longitude'] = $coordinates['longitude'] ?? null;
        }

        $pickupPoint->update($updateData);

        return $pickupPoint->fresh();
    }
}
