<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class GetAllPackagesAction
{
    /**
     * Get all platform-owned packages (where instructor_id is null).
     *
     * @return Collection<int, Package>
     */
    public function __invoke(): Collection
    {
        return Package::query()
            ->whereNull('instructor_id')
            ->orderByDesc('created_at')
            ->get();
    }
}
