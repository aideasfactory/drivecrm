<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\Derive;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class BusinessMilesToAllowanceAction
{
    /**
     * Compute the Simplified mileage-allowance contribution to carVanTravelExpenses
     * for one vehicle across a period. Honours HMRC's 45p / 25p banding which resets
     * each tax year (6 April). When a period straddles a tax-year boundary, each
     * side gets its own 10,000-mile band.
     *
     * If the vehicle was disposed inside the period, only miles up to and including
     * `disposed_on` are counted.
     *
     * @return array{
     *     pence: int,
     *     business_miles: int,
     *     segments: array<int, array{
     *         tax_year_start: string,
     *         segment_start: string,
     *         segment_end: string,
     *         miles_before_segment: int,
     *         miles_in_segment: int,
     *         first_band_miles: int,
     *         second_band_miles: int,
     *         pence: int,
     *     }>,
     * }
     */
    public function __invoke(Vehicle $vehicle, Carbon $periodStart, Carbon $periodEnd): array
    {
        $effectiveEnd = $this->effectiveEnd($vehicle, $periodEnd);
        if ($effectiveEnd->lt($periodStart)) {
            return ['pence' => 0, 'business_miles' => 0, 'segments' => []];
        }

        $firstBandRate = (int) config('hmrc.mileage_allowance.first_band_pence_per_mile', 45);
        $secondBandRate = (int) config('hmrc.mileage_allowance.second_band_pence_per_mile', 25);
        $firstBandCap = (int) config('hmrc.mileage_allowance.first_band_miles', 10000);

        $totalPence = 0;
        $totalMiles = 0;
        $segments = [];

        foreach ($this->splitByTaxYear($periodStart, $effectiveEnd) as $segment) {
            $taxYearStart = $segment['tax_year_start'];
            $segStart = $segment['start'];
            $segEnd = $segment['end'];

            $milesBefore = $this->sumBusinessMiles(
                $vehicle,
                $taxYearStart,
                $segStart->copy()->subDay(),
            );

            $milesInSegment = $this->sumBusinessMiles($vehicle, $segStart, $segEnd);

            $firstBandRemaining = max($firstBandCap - $milesBefore, 0);
            $firstBandUsed = min($milesInSegment, $firstBandRemaining);
            $secondBandUsed = max($milesInSegment - $firstBandUsed, 0);

            $segmentPence = ($firstBandUsed * $firstBandRate) + ($secondBandUsed * $secondBandRate);

            $totalPence += $segmentPence;
            $totalMiles += $milesInSegment;

            $segments[] = [
                'tax_year_start' => $taxYearStart->toDateString(),
                'segment_start' => $segStart->toDateString(),
                'segment_end' => $segEnd->toDateString(),
                'miles_before_segment' => $milesBefore,
                'miles_in_segment' => $milesInSegment,
                'first_band_miles' => $firstBandUsed,
                'second_band_miles' => $secondBandUsed,
                'pence' => $segmentPence,
            ];
        }

        return [
            'pence' => $totalPence,
            'business_miles' => $totalMiles,
            'segments' => $segments,
        ];
    }

    private function effectiveEnd(Vehicle $vehicle, Carbon $periodEnd): Carbon
    {
        if ($vehicle->disposed_on === null) {
            return $periodEnd->copy();
        }

        $disposedOn = $vehicle->disposed_on->copy();

        return $disposedOn->lt($periodEnd) ? $disposedOn : $periodEnd->copy();
    }

    /**
     * Split a date range at UK tax-year boundaries (6 April).
     *
     * @return array<int, array{tax_year_start: Carbon, start: Carbon, end: Carbon}>
     */
    private function splitByTaxYear(Carbon $start, Carbon $end): array
    {
        $segments = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $taxYearStart = $this->taxYearStartFor($cursor);
            $nextTaxYearStart = $taxYearStart->copy()->addYear();
            $segmentEnd = $nextTaxYearStart->copy()->subDay();
            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            $segments[] = [
                'tax_year_start' => $taxYearStart,
                'start' => $cursor->copy(),
                'end' => $segmentEnd,
            ];

            $cursor = $segmentEnd->copy()->addDay();
        }

        return $segments;
    }

    /**
     * The UK tax year start (6 April) on or before the given date.
     */
    private function taxYearStartFor(Carbon $date): Carbon
    {
        $aprilSixThisYear = Carbon::create($date->year, 4, 6)->startOfDay();
        if ($date->gte($aprilSixThisYear)) {
            return $aprilSixThisYear;
        }

        return Carbon::create($date->year - 1, 4, 6)->startOfDay();
    }

    private function sumBusinessMiles(Vehicle $vehicle, Carbon $from, Carbon $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }

        return (int) $vehicle->mileageLogs()
            ->where('type', 'business')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('miles');
    }
}
