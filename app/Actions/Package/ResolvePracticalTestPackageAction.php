<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Validation\ValidationException;

class ResolvePracticalTestPackageAction
{
    public function __construct(
        protected FindOrCreateOneOffPackageAction $findOrCreateOneOffPackage,
    ) {}

    /**
     * Work out what a paid driving test is charged at, the same way as Offer
     * Slot: a one-off price becomes a private 1-lesson One-Off Package (never
     * listed for pupils to buy), otherwise it must be one of the instructor's
     * own active single-lesson packages.
     *
     * @throws ValidationException
     */
    public function __invoke(Instructor $instructor, ?int $packageId, ?int $oneOffPricePence): Package
    {
        if ($oneOffPricePence !== null) {
            return ($this->findOrCreateOneOffPackage)($instructor, $oneOffPricePence, false);
        }

        $package = Package::query()
            ->whereKey($packageId)
            ->where('instructor_id', $instructor->id)
            ->where('active', true)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => 'Please select one of this instructor\'s active packages.',
            ]);
        }

        if ($package->lessons_count !== 1) {
            throw ValidationException::withMessages([
                'package_id' => 'Only single-lesson packages can be used for a driving test.',
            ]);
        }

        return $package;
    }
}
