<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Location;

class DeleteInstructorLocationAction
{
    /**
     * Delete a coverage location.
     *
     * @return bool Whether the deletion was successful
     */
    public function __invoke(Location $location): bool
    {
        return $location->delete();
    }
}
