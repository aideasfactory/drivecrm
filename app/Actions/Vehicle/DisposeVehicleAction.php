<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class DisposeVehicleAction
{
    public function __invoke(Vehicle $vehicle, Carbon|string|null $disposedOn = null): Vehicle
    {
        $vehicle->disposed_on = $disposedOn ?? Carbon::now()->toDateString();
        $vehicle->save();

        return $vehicle;
    }
}
