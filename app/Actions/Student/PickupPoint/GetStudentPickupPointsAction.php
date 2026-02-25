<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class GetStudentPickupPointsAction
{
    /**
     * Get all pickup points for a student, ordered by default first then by label.
     */
    public function __invoke(Student $student): Collection
    {
        return $student->pickupPoints()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }
}
