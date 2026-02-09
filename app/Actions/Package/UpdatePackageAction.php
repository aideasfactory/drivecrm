<?php

namespace App\Actions\Package;

use App\Models\Package;

class UpdatePackageAction
{
    /**
     * Update an existing package.
     */
    public function __invoke(Package $package, array $data): Package
    {
        $package->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_price_pence' => $data['total_price_pence'],
            'lessons_count' => $data['lessons_count'],
        ]);

        return $package->fresh();
    }
}
