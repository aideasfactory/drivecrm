<?php

declare(strict_types=1);

namespace App\Actions\Student\PickupPoint;

use App\Actions\Shared\LookupPostcodesInBulkAction;
use App\Models\StudentPickupPoint;
use Illuminate\Support\Collection;

class GeocodePickupPointsAction
{
    public function __construct(
        protected LookupPostcodesInBulkAction $lookupPostcodesInBulk,
    ) {}

    /**
     * Fill latitude/longitude from each point's postcode with one bulk
     * lookup (at most 100 distinct postcodes). Only points still missing
     * coordinates are written; nothing else on the point changes. Postcodes
     * postcodes.io doesn't know are left empty.
     *
     * @param  Collection<int, StudentPickupPoint>  $points
     * @return array{geocoded: int, not_found: int}
     */
    public function __invoke(Collection $points): array
    {
        $coordinates = ($this->lookupPostcodesInBulk)(
            $points->map(fn (StudentPickupPoint $point) => strtoupper(trim($point->postcode)))->unique()->values()->all()
        );

        $counts = ['geocoded' => 0, 'not_found' => 0];

        foreach ($points as $point) {
            $found = $coordinates[strtoupper(trim($point->postcode))] ?? null;

            if ($found === null) {
                $counts['not_found']++;

                continue;
            }

            $counts['geocoded'] += StudentPickupPoint::query()
                ->whereKey($point->id)
                ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->update(['latitude' => $found['latitude'], 'longitude' => $found['longitude']]);
        }

        return $counts;
    }
}
