<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Vehicle\BackfillPrimaryVehicleAction;
use App\Actions\Vehicle\CompareVehicleMethodsAction;
use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\DisposeVehicleAction;
use App\Actions\Vehicle\ReviewInsuranceSplitAction;
use App\Actions\Vehicle\SwitchVehicleMethodAction;
use App\Enums\VehicleMethod;
use App\Models\Instructor;
use App\Models\InstructorFinance;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class VehicleService extends BaseService
{
    public function __construct(
        protected CreateVehicleAction $createVehicle,
        protected SwitchVehicleMethodAction $switchMethod,
        protected CompareVehicleMethodsAction $compareMethods,
        protected BackfillPrimaryVehicleAction $backfillPrimary,
        protected ReviewInsuranceSplitAction $reviewInsurance,
        protected DisposeVehicleAction $disposeVehicle,
    ) {}

    /**
     * All vehicles for an instructor — active + disposed. Cached.
     *
     * @return Collection<int, Vehicle>
     */
    public function vehiclesFor(Instructor $instructor): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'vehicles');

        return $this->remember(
            $key,
            fn () => $instructor->vehicles()->orderBy('disposed_on')->orderBy('display_name')->get(),
        );
    }

    /**
     * Active vehicles only (no disposed_on). Cached.
     *
     * @return Collection<int, Vehicle>
     */
    public function activeVehiclesFor(Instructor $instructor): Collection
    {
        return $this->vehiclesFor($instructor)->filter(fn (Vehicle $v) => ! $v->isDisposed())->values();
    }

    public function createForInstructor(Instructor $instructor, array $attributes): Vehicle
    {
        $vehicle = ($this->createVehicle)($instructor, $attributes);

        $this->invalidateVehicleCache($instructor);

        return $vehicle;
    }

    public function updateVehicle(Vehicle $vehicle, array $attributes): Vehicle
    {
        $vehicle->fill($attributes);
        $vehicle->save();

        $this->invalidateVehicleCache($vehicle->instructor);

        return $vehicle->fresh() ?? $vehicle;
    }

    public function dispose(Vehicle $vehicle, Carbon|string|null $disposedOn = null): Vehicle
    {
        $vehicle = ($this->disposeVehicle)($vehicle, $disposedOn);

        $this->invalidateVehicleCache($vehicle->instructor);

        return $vehicle;
    }

    /**
     * @return array{
     *     status: 'unchanged'|'requires_confirmation'|'switched',
     *     vehicle: Vehicle,
     *     from: VehicleMethod,
     *     to: VehicleMethod,
     *     message?: string,
     * }
     */
    public function switchMethod(Vehicle $vehicle, VehicleMethod $target, bool $confirmed = false): array
    {
        $result = ($this->switchMethod)($vehicle, $target, $confirmed);

        if ($result['status'] === 'switched') {
            $this->invalidateVehicleCache($vehicle->instructor);
        }

        return $result;
    }

    /**
     * @return array{
     *     simplified_pence: int,
     *     actual_pence: int,
     *     business_miles: int,
     *     vehicle_running_costs_pence: int,
     *     business_use_percentage: float,
     * }
     */
    public function compareMethods(Vehicle $vehicle, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        return ($this->compareMethods)($vehicle, $rangeStart, $rangeEnd);
    }

    public function backfillPrimaryFor(Instructor $instructor, array $attributes): array
    {
        $result = ($this->backfillPrimary)($instructor, $attributes);

        $this->invalidateVehicleCache($instructor);

        return $result;
    }

    /**
     * @param  array<int, array{finance_row_id: int, target_category: string}>  $decisions
     */
    public function applyInsuranceReview(Instructor $instructor, array $decisions): int
    {
        return ($this->reviewInsurance)($instructor, $decisions);
    }

    /**
     * @return Collection<int, InstructorFinance>
     */
    public function pendingInsuranceReviewRows(Instructor $instructor): Collection
    {
        return $instructor->finances()
            ->where('category', 'insurance')
            ->orderBy('date', 'desc')
            ->get();
    }

    protected function invalidateVehicleCache(?Instructor $instructor): void
    {
        if ($instructor === null) {
            return;
        }

        $this->invalidate(
            $this->cacheKey('instructor', $instructor->id, 'vehicles')
        );
    }
}
