<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\StudentPickupPoint;
use Illuminate\Support\Facades\DB;

class CreateImportedPickupPointsAction
{
    public const FIRST_LABEL = 'Home';

    public const OTHER_LABEL = 'Pick-up point';

    /**
     * The pickup points a pupil gets from their clean locations (in order):
     * the first is the default; an unlabelled first one is called "Home" and
     * any other unlabelled one "Pick-up point". No coordinates — geocoding
     * is a separate step.
     *
     * @param  array<int, array{label: string|null, address: string, postcode: string}>  $cleanLocations
     * @return array<int, array{label: string, address: string, postcode: string, is_default: bool}>
     */
    public static function plan(array $cleanLocations): array
    {
        $points = [];

        foreach (array_values($cleanLocations) as $index => $location) {
            $points[] = [
                'label' => $location['label'] ?? ($index === 0 ? self::FIRST_LABEL : self::OTHER_LABEL),
                'address' => $location['address'],
                'postcode' => $location['postcode'],
                'is_default' => $index === 0,
            ];
        }

        return $points;
    }

    /**
     * Save the planned pickup points, unless the pupil has any pickup point by
     * now (checked again under a lock, so a re-run or a point added meanwhile
     * is left alone).
     *
     * @param  array<int, array{label: string, address: string, postcode: string, is_default: bool}>  $points  From plan()
     * @return bool Whether they were saved
     */
    public function __invoke(int $studentId, array $points): bool
    {
        return DB::transaction(function () use ($studentId, $points): bool {
            if (StudentPickupPoint::query()->where('student_id', $studentId)->lockForUpdate()->exists()) {
                return false;
            }

            foreach ($points as $point) {
                StudentPickupPoint::create($point + ['student_id' => $studentId, 'latitude' => null, 'longitude' => null]);
            }

            return true;
        });
    }
}
