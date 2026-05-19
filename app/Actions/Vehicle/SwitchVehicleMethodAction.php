<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Enums\VehicleMethod;
use App\Models\Vehicle;

class SwitchVehicleMethodAction
{
    /**
     * Switch a vehicle's method, with soft-lock awareness.
     *
     * Returns a status array. The caller is responsible for re-invoking with
     * confirmed=true once the user accepts the soft-lock warning in the UI.
     *
     * @return array{
     *     status: 'unchanged'|'requires_confirmation'|'switched',
     *     vehicle: Vehicle,
     *     from: VehicleMethod,
     *     to: VehicleMethod,
     *     message?: string,
     * }
     */
    public function __invoke(
        Vehicle $vehicle,
        VehicleMethod $target,
        bool $confirmed = false,
    ): array {
        $current = $vehicle->method;

        if ($current === $target) {
            return [
                'status' => 'unchanged',
                'vehicle' => $vehicle,
                'from' => $current,
                'to' => $target,
            ];
        }

        if ($vehicle->methodLocked() && ! $confirmed) {
            return [
                'status' => 'requires_confirmation',
                'vehicle' => $vehicle,
                'from' => $current,
                'to' => $target,
                'message' => 'This vehicle has been used for a submitted ITSA quarterly. Switching method is a permanent HMRC-tracked decision — confirm to proceed.',
            ];
        }

        $vehicle->method = $target;
        $vehicle->save();

        return [
            'status' => 'switched',
            'vehicle' => $vehicle->fresh() ?? $vehicle,
            'from' => $current,
            'to' => $target,
        ];
    }
}
