<?php

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorPackagesAction
{
    /**
     * Get all packages available to an instructor (platform + bespoke).
     *
     * @param  bool  $onlyActive  Filter to only active packages
     * @return Collection<int, Package>
     */
    public function __invoke(Instructor $instructor, bool $onlyActive = true): Collection
    {
        $query = Package::query()->where('instructor_id', $instructor->id);

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get();
    }
}
