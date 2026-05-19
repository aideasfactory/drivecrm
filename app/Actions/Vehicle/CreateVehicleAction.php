<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Enums\VehicleMethod;
use App\Models\Instructor;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class CreateVehicleAction
{
    /**
     * @param  array{
     *     display_name: string,
     *     registration?: ?string,
     *     engine_size_cc?: ?int,
     *     method?: VehicleMethod|string|null,
     *     business_use_percentage?: ?float,
     *     acquired_on: Carbon|string,
     * }  $attributes
     */
    public function __invoke(Instructor $instructor, array $attributes): Vehicle
    {
        $method = $this->resolveMethod($attributes['method'] ?? null);

        return Vehicle::create([
            'instructor_id' => $instructor->id,
            'display_name' => $attributes['display_name'],
            'registration' => $attributes['registration'] ?? null,
            'engine_size_cc' => $attributes['engine_size_cc'] ?? null,
            'method' => $method->value,
            'business_use_percentage' => $attributes['business_use_percentage']
                ?? (float) config('hmrc.actual_default_business_use_pct', 95),
            'acquired_on' => $attributes['acquired_on'],
        ]);
    }

    private function resolveMethod(VehicleMethod|string|null $method): VehicleMethod
    {
        if ($method instanceof VehicleMethod) {
            return $method;
        }

        if (is_string($method) && $method !== '') {
            return VehicleMethod::from($method);
        }

        return VehicleMethod::Simplified;
    }
}
