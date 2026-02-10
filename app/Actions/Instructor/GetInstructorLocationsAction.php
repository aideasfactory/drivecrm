<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorLocationsAction
{
    /**
     * Get all coverage locations for an instructor.
     *
     * @return Collection Collection of locations with id and postcode_sector
     */
    public function __invoke(Instructor $instructor): Collection
    {
        return $instructor->locations()
            ->orderBy('postcode_sector')
            ->get()
            ->map(fn ($location) => [
                'id' => $location->id,
                'postcode_sector' => $location->postcode_sector,
            ]);
    }
}
