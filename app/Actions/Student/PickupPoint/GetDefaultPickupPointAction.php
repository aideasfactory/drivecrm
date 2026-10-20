<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Models\Student;
use App\Models\StudentPickupPoint;

class GetDefaultPickupPointAction
{
    /**
     * The pupil's default pickup point. Falls back to their oldest pickup
     * point when none is flagged as default, matching the "Lessons booked"
     * page. Returns null when the pupil has no pickup points.
     */
    public function __invoke(Student $student): ?StudentPickupPoint
    {
        return $student->pickupPoints()
            ->orderByDesc('is_default')
            ->oldest()
            ->orderBy('id')
            ->first();
    }
}
