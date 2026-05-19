<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Models\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WriteMileageCsvAction
{
    /**
     * Write `mileage.csv` inside the staging directory.
     */
    public function __invoke(string $stagingDir, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        $path = $stagingDir.'/mileage.csv';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Could not open mileage.csv for writing.');
        }

        fputcsv($handle, [
            'date',
            'type',
            'vehicle_id',
            'vehicle_display_name',
            'vehicle_method',
            'start_mileage',
            'end_mileage',
            'miles',
            'notes',
            'created_at',
        ]);

        $rows = DB::table('mileage_logs')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'mileage_logs.vehicle_id')
            ->where('mileage_logs.instructor_id', $instructor->id)
            ->whereBetween('mileage_logs.date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('mileage_logs.date')
            ->select(
                'mileage_logs.*',
                'vehicles.display_name as vehicle_display_name',
                'vehicles.method as vehicle_method',
            )
            ->cursor();

        $count = 0;
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->date,
                $row->type,
                $row->vehicle_id,
                $row->vehicle_display_name,
                $row->vehicle_method,
                $row->start_mileage,
                $row->end_mileage,
                $row->miles,
                $row->notes,
                $row->created_at,
            ]);
            $count++;
        }

        fclose($handle);

        return $count;
    }
}
