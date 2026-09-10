<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Models\Package;
use InvalidArgumentException;

class SetInstructorPackageActiveAction
{
    public function __construct(
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Hide or restore an instructor-owned package without deleting the row.
     */
    public function __invoke(Package $package, bool $active): Package
    {
        if ($package->instructor_id === null) {
            throw new InvalidArgumentException('Platform packages cannot be hidden with this action.');
        }

        if ($package->active === $active) {
            return $package;
        }

        $package->update(['active' => $active]);

        $verb = $active ? 'restored' : 'deactivated';

        ($this->logActivity)(
            $package->instructor,
            "Package '{$package->name}' {$verb}",
            'package',
            [
                'package_id' => $package->id,
                'active' => $active,
            ],
        );

        return $package;
    }
}
