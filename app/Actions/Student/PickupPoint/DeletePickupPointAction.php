<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Models\StudentPickupPoint;

class DeletePickupPointAction
{
    /**
     * Delete a pickup point.
     */
    public function __invoke(StudentPickupPoint $pickupPoint): void
    {
        $pickupPoint->delete();
    }
}
