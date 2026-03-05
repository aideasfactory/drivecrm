<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class GetAllPackagesAction
{
    /**
     * Get all packages with their associated instructor.
     *
     * @return Collection<int, Package>
     */
    public function __invoke(): Collection
    {
        return Package::query()
            ->with('instructor')
            ->orderByDesc('created_at')
            ->get();
    }
}
