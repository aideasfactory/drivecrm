<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Models\StudentPickupPoint;

class SetDefaultPickupPointAction
{
    /**
     * Set a pickup point as default, unsetting all others for the same student.
     */
    public function __invoke(StudentPickupPoint $pickupPoint): StudentPickupPoint
    {
        // Unset all other defaults for this student
        $pickupPoint->student->pickupPoints()
            ->where('id', '!=', $pickupPoint->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this one as default
        $pickupPoint->update(['is_default' => true]);

        return $pickupPoint->fresh();
    }
}
