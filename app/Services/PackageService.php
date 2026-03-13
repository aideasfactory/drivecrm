<?php

namespace App\Services;

use App\Actions\Package\CreateAdminPackageAction;
use App\Actions\Package\GetAllPackagesAction;
use App\Actions\Package\UpdatePackageAction;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class PackageService
{
    public function __construct(
        protected GetAllPackagesAction $getAllPackages,
        protected CreateAdminPackageAction $createAdminPackage,
        protected UpdatePackageAction $updatePackage
    ) {}

    /**
     * Get all platform-owned packages.
     *
     * @return Collection<int, Package>
     */
    public function getAll(): Collection
    {
        return ($this->getAllPackages)();
    }

    /**
     * Create a new admin-level package (no instructor).
     *
     * @param  array{name: string, description: ?string, total_price_pence: int, lessons_count: int}  $data
     */
    public function createAdminPackage(array $data): Package
    {
        return ($this->createAdminPackage)($data);
    }

    /**
     * Update an existing package.
     */
    public function update(Package $package, array $data): Package
    {
        return ($this->updatePackage)($package, $data);
    }
}
