<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\Derive;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ActualVehicleCostsAction
{
    /**
     * Sum the apportioned actual running costs for a vehicle in a period. Includes
     * only categories where `category_tax_mapping.method_dependent = true` and
     * `claimable = true` — that is the fuel/insurance/MOT/servicing/repairs/road_tax/
     * breakdown_cover/vehicle_insurance set defined in Phase 6.
     *
     * Multiplies the raw total by the vehicle's `business_use_percentage`.
     *
     * Disposed mid-period: only rows dated up to `disposed_on` are summed.
     *
     * @return array{
     *     pence: int,
     *     raw_pence: int,
     *     business_use_percentage: float,
     * }
     */
    public function __invoke(Vehicle $vehicle, Carbon $periodStart, Carbon $periodEnd): array
    {
        $effectiveEnd = $vehicle->disposed_on !== null && $vehicle->disposed_on->lt($periodEnd)
            ? $vehicle->disposed_on->copy()
            : $periodEnd->copy();

        if ($effectiveEnd->lt($periodStart)) {
            return [
                'pence' => 0,
                'raw_pence' => 0,
                'business_use_percentage' => (float) $vehicle->business_use_percentage,
            ];
        }

        $rawPence = (int) DB::table('instructor_finances')
            ->join('category_tax_mapping', 'category_tax_mapping.category', '=', 'instructor_finances.category')
            ->where('instructor_finances.vehicle_id', $vehicle->id)
            ->where('instructor_finances.type', 'expense')
            ->where('category_tax_mapping.claimable', true)
            ->where('category_tax_mapping.method_dependent', true)
            ->whereBetween('instructor_finances.date', [$periodStart->toDateString(), $effectiveEnd->toDateString()])
            ->sum('instructor_finances.amount_pence');

        $businessUsePct = (float) $vehicle->business_use_percentage;
        $pence = (int) round($rawPence * ($businessUsePct / 100));

        return [
            'pence' => $pence,
            'raw_pence' => $rawPence,
            'business_use_percentage' => $businessUsePct,
        ];
    }
}
