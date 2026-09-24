<?php

namespace App\Actions\Instructor;

use App\Enums\PaymentMode;
use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorPackagesAction
{
    /**
     * Get all packages available to an instructor (platform + bespoke),
     * excluding the internal package that holds imported lessons.
     *
     * @param  bool  $onlyActive  Filter to only active packages
     * @return Collection<int, Package>
     */
    public function __invoke(Instructor $instructor, bool $onlyActive = true): Collection
    {
        // The hidden "Imported lessons" package only exists to hold imported
        // orders — never list it, so it can't be restored and sold at £0.
        $query = Package::query()
            ->where('instructor_id', $instructor->id)
            ->whereDoesntHave('orders', fn ($orders) => $orders->where('payment_mode', PaymentMode::IMPORTED));

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get();
    }
}
