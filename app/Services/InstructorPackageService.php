<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\CreateInstructorPackageAction;
use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Actions\Package\UpdatePackageAction;
use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class InstructorPackageService extends BaseService
{
    public function __construct(
        protected GetInstructorPackagesAction $getInstructorPackages,
        protected CreateInstructorPackageAction $createInstructorPackage,
        protected UpdatePackageAction $updatePackage
    ) {}

    /**
     * Get active packages for an instructor.
     *
     * @return Collection<int, Package>
     */
    public function getPackages(Instructor $instructor): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'packages');

        return $this->remember($key, fn () => ($this->getInstructorPackages)($instructor));
    }

    /**
     * Create a new bespoke package for an instructor.
     */
    public function createPackage(Instructor $instructor, array $data): Package
    {
        $package = ($this->createInstructorPackage)($instructor, $data);

        $this->invalidatePackageCache($instructor);

        return $package;
    }

    /**
     * Update an existing instructor package.
     */
    public function updatePackage(Package $package, array $data): Package
    {
        $updated = ($this->updatePackage)($package, $data);

        if ($package->instructor_id) {
            $this->invalidatePackageCache($package->instructor);
        }

        return $updated;
    }

    /**
     * Invalidate cached packages for an instructor.
     */
    protected function invalidatePackageCache(Instructor $instructor): void
    {
        $this->invalidate(
            $this->cacheKey('instructor', $instructor->id, 'packages')
        );
    }
}
