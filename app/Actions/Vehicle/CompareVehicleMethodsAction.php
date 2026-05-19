<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompareVehicleMethodsAction
{
    /**
     * Compute the deduction in pence that the two methods would give for a vehicle
     * across the supplied window.
     *
     * Simplified approximation for the comparison panel: the 45p/25p band is
     * applied across the window itself rather than per tax year, so it can
     * very slightly over- or under-estimate the Simplified figure when the
     * window straddles 6 April. Phase 7's auto-derivation handles that
     * properly. The comparison panel is a guide, not a tax filing.
     *
     * @return array{
     *     simplified_pence: int,
     *     actual_pence: int,
     *     business_miles: int,
     *     vehicle_running_costs_pence: int,
     *     business_use_percentage: float,
     * }
     */
    public function __invoke(Vehicle $vehicle, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $businessMiles = (int) $vehicle->mileageLogs()
            ->where('type', 'business')
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->sum('miles');

        $firstBandMiles = (int) config('hmrc.mileage_allowance.first_band_miles', 10000);
        $firstBandRate = (int) config('hmrc.mileage_allowance.first_band_pence_per_mile', 45);
        $secondBandRate = (int) config('hmrc.mileage_allowance.second_band_pence_per_mile', 25);

        $firstBandUsed = min($businessMiles, $firstBandMiles);
        $secondBandUsed = max($businessMiles - $firstBandMiles, 0);
        $simplifiedPence = ($firstBandUsed * $firstBandRate) + ($secondBandUsed * $secondBandRate);

        $runningCostsPence = (int) DB::table('instructor_finances')
            ->join('category_tax_mapping', 'category_tax_mapping.category', '=', 'instructor_finances.category')
            ->where('instructor_finances.vehicle_id', $vehicle->id)
            ->where('instructor_finances.type', 'expense')
            ->where('category_tax_mapping.claimable', true)
            ->where('category_tax_mapping.method_dependent', true)
            ->whereBetween('instructor_finances.date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->sum('instructor_finances.amount_pence');

        $businessUsePct = (float) $vehicle->business_use_percentage;
        $actualPence = (int) round($runningCostsPence * ($businessUsePct / 100));

        return [
            'simplified_pence' => $simplifiedPence,
            'actual_pence' => $actualPence,
            'business_miles' => $businessMiles,
            'vehicle_running_costs_pence' => $runningCostsPence,
            'business_use_percentage' => $businessUsePct,
        ];
    }
}
