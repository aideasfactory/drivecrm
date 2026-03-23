<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

class InstructorPackageService extends BaseService
{
    public function __construct(
        protected GetInstructorPackagesAction $getInstructorPackages
    ) {}

    /**
     * Get active packages for an instructor.
     *
     * @return Collection<int, \App\Models\Package>
     */
    public function getPackages(Instructor $instructor): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'packages');

        return $this->remember($key, fn () => ($this->getInstructorPackages)($instructor));
    }
}
