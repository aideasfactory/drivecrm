<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;
use Illuminate\Validation\ValidationException;

class DeletePackageAction
{
    /**
     * Delete a package that has never been booked.
     *
     * A package with orders is refused: deleting it would remove those orders and their
     * lessons, payments and payouts (orders.package_id is also ON DELETE RESTRICT as a backstop).
     *
     * @throws ValidationException
     */
    public function __invoke(Package $package): void
    {
        $ordersCount = $package->orders()->count();

        if ($ordersCount > 0) {
            throw ValidationException::withMessages([
                'package' => "\"{$package->name}\" can't be deleted because it has {$ordersCount} "
                    .($ordersCount === 1 ? 'booking' : 'bookings')
                    .'. To stop pupils booking it, edit it and turn off "Visible to pupils".',
            ]);
        }

        $package->delete();
    }
}
