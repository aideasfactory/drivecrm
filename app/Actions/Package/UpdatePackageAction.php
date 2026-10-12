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
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_price_pence' => $data['total_price_pence'],
            'lessons_count' => $data['lessons_count'],
        ];

        if (array_key_exists('visible_to_pupils', $data)) {
            $attributes['visible_to_pupils'] = Package::visibilityFromInput($data);
        }

        $package->update($attributes);

        return $package->fresh();
    }
}
