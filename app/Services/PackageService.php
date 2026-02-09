<?php

namespace App\Services;

use App\Actions\Package\UpdatePackageAction;
use App\Models\Package;

class PackageService
{
    public function __construct(
        protected UpdatePackageAction $updatePackage
    ) {}

    /**
     * Update an existing package.
     */
    public function update(Package $package, array $data): Package
    {
        return ($this->updatePackage)($package, $data);
    }
}
