<?php

declare(strict_types=1);

namespace App\Actions\Instructor\Mileage;

use App\Models\MileageLog;

class UpdateMileageLogAction
{
    public function __invoke(MileageLog $log, array $data): MileageLog
    {
        $start = $data['start_mileage'] ?? $log->start_mileage;
        $end = $data['end_mileage'] ?? $log->end_mileage;

        $payload = array_filter([
            'date' => $data['date'] ?? null,
            'start_mileage' => $data['start_mileage'] ?? null,
            'end_mileage' => $data['end_mileage'] ?? null,
            'type' => $data['type'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
        ], fn ($v) => $v !== null);

        // Keep miles in sync with start/end whenever either changes.
        if (array_key_exists('start_mileage', $data) || array_key_exists('end_mileage', $data)) {
            $payload['miles'] = $end - $start;
        }

        // `notes` can legitimately be set to null — re-add if the key was provided.
        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $data['notes'];
        }

        $log->update($payload);

        return $log->fresh();
    }
}
