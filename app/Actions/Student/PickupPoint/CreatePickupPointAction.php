<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Actions\Shared\LookupPostcodeAction;
use App\Models\Student;
use App\Models\StudentPickupPoint;

class CreatePickupPointAction
{
    public function __construct(
        protected LookupPostcodeAction $lookupPostcode
    ) {}

    /**
     * Create a new pickup point for a student, geocoding the postcode.
     *
     * @param  array{label: string, address: string, postcode: string, is_default?: bool}  $data
     */
    public function __invoke(Student $student, array $data): StudentPickupPoint
    {
        // If setting as default, unset any existing defaults
        if (! empty($data['is_default'])) {
            $student->pickupPoints()->where('is_default', true)->update(['is_default' => false]);
        }

        // Geocode the postcode
        $coordinates = ($this->lookupPostcode)($data['postcode']);

        return $student->pickupPoints()->create([
            'label' => $data['label'],
            'address' => $data['address'],
            'postcode' => strtoupper(trim($data['postcode'])),
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);
    }
}
