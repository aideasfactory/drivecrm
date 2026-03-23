<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Support\Collection;

class InstructorPackageService extends BaseService
{
    /**
     * Get active packages for an instructor.
     */
    public function getPackages(Instructor $instructor): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'packages');

        return $this->remember($key, fn () => Package::query()
            ->where('instructor_id', $instructor->id)
            ->where('active', true)
            ->orderBy('total_price_pence')
            ->get()
        );
    }
}
