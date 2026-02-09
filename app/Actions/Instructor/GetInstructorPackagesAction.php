<?php

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Support\Collection;

class GetInstructorPackagesAction
{
    /**
     * Get all packages available to an instructor (platform + bespoke).
     *
     * @param  bool  $onlyActive  Filter to only active packages
     */
    public function __invoke(Instructor $instructor, bool $onlyActive = true): Collection
    {
        $query = Package::query()->where('instructor_id', $instructor->id);

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get()->map(fn (Package $package) => [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'total_price_pence' => $package->total_price_pence,
            'lessons_count' => $package->lessons_count,
            'lesson_price_pence' => $package->lesson_price_pence,
            'formatted_total_price' => $package->formatted_total_price,
            'formatted_lesson_price' => $package->formatted_lesson_price,
            'active' => $package->active,
            'is_platform_package' => $package->isPlatformPackage(),
            'is_bespoke_package' => $package->isBespokePackage(),
        ]);
    }
}
