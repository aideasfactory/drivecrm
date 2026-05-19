<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\Derive;

use App\Enums\ItsaExpenseCategory;
use App\Enums\LessonStatus;
use App\Enums\VehicleMethod;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeriveItsaQuarterlyTotalsAction
{
    public function __construct(
        private readonly BusinessMilesToAllowanceAction $mileageToAllowance,
        private readonly ActualVehicleCostsAction $actualVehicleCosts,
    ) {}

    /**
     * Derive the quarterly turnover + itemised expense buckets for an instructor
     * across the given period. Per-vehicle math chooses Simplified vs Actual based
     * on the vehicle's current `method` (mid-period switches use the current method
     * for the whole period — see vehicles-and-method-choice.md Phase 7 edge cases).
     *
     * Non-vehicle expense categories (advertising, accountant fees, business
     * insurance, etc.) are summed straight from `instructor_finances` grouped by
     * `category_tax_mapping.itsa_bucket`. Categories with `claimable = false` or
     * `selectable_in_picker = false` (e.g. `food_drink`) are excluded.
     *
     * @return array{
     *     turnover_pence: int,
     *     other_income_pence: int,
     *     expenses_pence: array<string, int>,
     *     diagnostics: array{
     *         vehicles: array<int, array{
     *             vehicle_id: int,
     *             display_name: string,
     *             method: string,
     *             pence: int,
     *             detail: array<string, mixed>,
     *         }>,
     *         non_vehicle_buckets: array<string, int>,
     *         missing_primary_vehicle: bool,
     *     },
     * }
     */
    public function __invoke(Instructor $instructor, Carbon $periodStart, Carbon $periodEnd): array
    {
        $hasVehicles = $instructor->vehicles()->exists();
        $orphanMileage = $instructor->mileageLogs()
            ->where('type', 'business')
            ->whereNull('vehicle_id')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->exists();

        $expenses = [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $expenses[$category->value] = 0;
        }

        $vehicleDiagnostics = [];
        $vehicleCarVanPence = 0;

        $vehicles = $instructor->vehicles()
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('disposed_on')->orWhere('disposed_on', '>=', $periodStart->toDateString());
            })
            ->get();

        foreach ($vehicles as $vehicle) {
            $result = $this->derivePerVehicle($vehicle, $periodStart, $periodEnd);
            $vehicleCarVanPence += $result['pence'];
            $vehicleDiagnostics[] = [
                'vehicle_id' => $vehicle->id,
                'display_name' => $vehicle->display_name,
                'method' => $vehicle->method->value,
                'pence' => $result['pence'],
                'detail' => $result['detail'],
            ];
        }

        $expenses[ItsaExpenseCategory::CarVanTravelExpenses->value] = $vehicleCarVanPence;

        $nonVehicleBuckets = $this->sumNonVehicleBucketsByHmrcKey($instructor, $periodStart, $periodEnd);

        $hmrcKeyToValue = $this->hmrcKeyToValueMap();
        foreach ($nonVehicleBuckets as $hmrcKey => $pence) {
            if (! isset($hmrcKeyToValue[$hmrcKey])) {
                continue;
            }
            $expenses[$hmrcKeyToValue[$hmrcKey]] += $pence;
        }

        $turnoverPence = $this->sumTurnoverPence($instructor, $periodStart, $periodEnd);
        $otherIncomePence = $this->sumOtherIncomePence($instructor, $periodStart, $periodEnd);

        return [
            'turnover_pence' => $turnoverPence,
            'other_income_pence' => $otherIncomePence,
            'expenses_pence' => $expenses,
            'diagnostics' => [
                'vehicles' => $vehicleDiagnostics,
                'non_vehicle_buckets' => $nonVehicleBuckets,
                'missing_primary_vehicle' => ! $hasVehicles || $orphanMileage,
            ],
        ];
    }

    /**
     * @return array{pence: int, detail: array<string, mixed>}
     */
    private function derivePerVehicle(Vehicle $vehicle, Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($vehicle->method === VehicleMethod::Simplified) {
            $result = ($this->mileageToAllowance)($vehicle, $periodStart, $periodEnd);

            return [
                'pence' => $result['pence'],
                'detail' => [
                    'method' => 'simplified',
                    'business_miles' => $result['business_miles'],
                    'segments' => $result['segments'],
                ],
            ];
        }

        $result = ($this->actualVehicleCosts)($vehicle, $periodStart, $periodEnd);

        return [
            'pence' => $result['pence'],
            'detail' => [
                'method' => 'actual',
                'raw_pence' => $result['raw_pence'],
                'business_use_percentage' => $result['business_use_percentage'],
            ],
        ];
    }

    /**
     * Sum non-vehicle finance rows grouped by HMRC bucket key (camelCase).
     * Excludes method-dependent categories (they were handled per-vehicle above).
     *
     * @return array<string, int>
     */
    private function sumNonVehicleBucketsByHmrcKey(Instructor $instructor, Carbon $periodStart, Carbon $periodEnd): array
    {
        $rows = DB::table('instructor_finances')
            ->join('category_tax_mapping', 'category_tax_mapping.category', '=', 'instructor_finances.category')
            ->where('instructor_finances.instructor_id', $instructor->id)
            ->where('instructor_finances.type', 'expense')
            ->where('category_tax_mapping.claimable', true)
            ->where('category_tax_mapping.method_dependent', false)
            ->whereNotNull('category_tax_mapping.itsa_bucket')
            ->whereBetween('instructor_finances.date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->groupBy('category_tax_mapping.itsa_bucket')
            ->selectRaw('category_tax_mapping.itsa_bucket as bucket, SUM(instructor_finances.amount_pence) as pence_total')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->bucket] = (int) $row->pence_total;
        }

        return $out;
    }

    /**
     * Sum completed-lesson income (turnover) for the period. Lessons are dated
     * by `date` (not a single datetime), and stored as `amount_pence`.
     */
    private function sumTurnoverPence(Instructor $instructor, Carbon $periodStart, Carbon $periodEnd): int
    {
        return (int) Lesson::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('status', LessonStatus::COMPLETED)
            ->sum('amount_pence');
    }

    /**
     * "Other income" on the HMRC ITSA payload is an optional miscellaneous-income
     * field separate from lesson turnover. v1 has no DRIVE category that maps to
     * it, so this is always 0 and the instructor can manually override on Period.vue.
     */
    private function sumOtherIncomePence(Instructor $instructor, Carbon $periodStart, Carbon $periodEnd): int
    {
        return 0;
    }

    /**
     * @return array<string, string>  hmrcKey => enum value
     */
    private function hmrcKeyToValueMap(): array
    {
        $map = [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $map[$category->hmrcKey()] = $category->value;
        }

        return $map;
    }
}
