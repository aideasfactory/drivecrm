<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Instructor;
use App\Models\Package;

class FindOrCreateOneOffPackageAction
{
    /**
     * Find or create a reusable 1-lesson "One-Off Package" at the given price.
     */
    public function __invoke(Instructor $instructor, int $pricePence): Package
    {
        return Package::query()->firstOrCreate(
            [
                'instructor_id' => $instructor->id,
                'is_one_off' => true,
                'total_price_pence' => $pricePence,
                'lessons_count' => 1,
            ],
            [
                'name' => 'One-Off Package',
                'description' => 'Short-notice one-off lesson',
                'active' => true,
            ]
        );
    }
}
