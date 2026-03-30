<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;

class DeletePackageAction
{
    /**
     * Delete a package.
     */
    public function __invoke(Package $package): void
    {
        $package->delete();
    }
}
