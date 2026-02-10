<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Location;

class CreateInstructorLocationAction
{
    /**
     * Create a new coverage location for an instructor.
     *
     * @param  string  $postcodeSector  Postcode sector (e.g., "TS7", "WR14")
     * @return Location The created location
     */
    public function __invoke(Instructor $instructor, string $postcodeSector): Location
    {
        return Location::create([
            'instructor_id' => $instructor->id,
            'postcode_sector' => strtoupper(trim($postcodeSector)),
        ]);
    }
}
