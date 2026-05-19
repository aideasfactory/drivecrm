<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Enums\VehicleMethod;
use App\Models\Instructor;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillPrimaryVehicleAction
{
    public function __construct(
        private readonly CreateVehicleAction $createVehicle,
    ) {}

    /**
     * Create the instructor's first vehicle and bulk-assign existing vehicle-bound
     * finance rows + all mileage logs to it. Single transaction.
     *
     * @param  array{
     *     display_name: string,
     *     registration?: ?string,
     *     engine_size_cc?: ?int,
     *     method?: VehicleMethod|string|null,
     *     business_use_percentage?: ?float,
     *     acquired_on: Carbon|string,
     * }  $attributes
     * @return array{vehicle: Vehicle, finance_rows_tagged: int, mileage_rows_tagged: int}
     */
    public function __invoke(Instructor $instructor, array $attributes): array
    {
        return DB::transaction(function () use ($instructor, $attributes): array {
            $vehicle = ($this->createVehicle)($instructor, $attributes);

            $vehicleBoundCategories = DB::table('category_tax_mapping')
                ->where('method_dependent', true)
                ->pluck('category')
                ->all();

            $financeRowsTagged = DB::table('instructor_finances')
                ->where('instructor_id', $instructor->id)
                ->whereNull('vehicle_id')
                ->whereIn('category', $vehicleBoundCategories)
                ->update(['vehicle_id' => $vehicle->id, 'updated_at' => now()]);

            $mileageRowsTagged = DB::table('mileage_logs')
                ->where('instructor_id', $instructor->id)
                ->whereNull('vehicle_id')
                ->update(['vehicle_id' => $vehicle->id, 'updated_at' => now()]);

            return [
                'vehicle' => $vehicle,
                'finance_rows_tagged' => $financeRowsTagged,
                'mileage_rows_tagged' => $mileageRowsTagged,
            ];
        });
    }
}
