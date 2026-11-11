<?php

declare(strict_types=1);

namespace App\Actions\Instructor\Mileage;

use App\Models\Instructor;
use App\Models\MileageLog;

class CreateMileageLogAction
{
    public function __invoke(Instructor $instructor, array $data): MileageLog
    {
        return $instructor->mileageLogs()->create([
            'date' => $data['date'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'start_mileage' => $data['start_mileage'],
            'end_mileage' => $data['end_mileage'] ?? null,
            'miles' => MileageLog::calculateMiles((int) $data['start_mileage'], $data['end_mileage'] ?? null),
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
