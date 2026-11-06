<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\MileageLog;
use App\Support\ImportValues;

class ImportMileageLogAction
{
    /**
     * Turn one mileage.csv row into a trip, or say why it doesn't import
     * cleanly. Clean = a parseable date, whole-number start and end odometer
     * with end ≥ start, and type Business or Personal. Miles = end − start.
     * No vehicle is set (the primary-vehicle setup tags trips later); the
     * row's registration, and its end date when the trip ran over more than
     * one day, are kept in the notes.
     *
     * @param  array<string, string|int>  $row
     * @return array{data: array{date: string, start_mileage: int, end_mileage: int, miles: int, type: string, notes: string|null}|null, skip_reason: string|null}
     */
    public static function parse(array $row): array
    {
        $date = ImportValues::date((string) $row['date']);
        $start = trim((string) $row['start_odometer']);
        $end = trim((string) $row['end_odometer']);
        $type = strtolower(trim((string) $row['type']));

        $skip = match (true) {
            $date === null => 'Date missing or not a date',
            ! ctype_digit($start) || ! ctype_digit($end) => 'Start or end odometer missing',
            (int) $end < (int) $start => 'End odometer below start',
            ! in_array($type, ['business', 'personal'], true) => 'Type is not Business or Personal',
            default => null,
        };

        if ($skip !== null) {
            return ['data' => null, 'skip_reason' => $skip];
        }

        $notes = [];
        $registration = trim((string) $row['vehicle_reg']);

        if ($registration !== '') {
            $notes[] = "Vehicle: {$registration}";
        }

        $endDate = ImportValues::date((string) $row['end_date']);

        if ($endDate !== null && ! $endDate->isSameDay($date)) {
            $notes[] = 'End date: '.$endDate->format('d/m/Y');
        }

        return [
            'data' => [
                'date' => $date->toDateString(),
                'start_mileage' => (int) $start,
                'end_mileage' => (int) $end,
                'miles' => (int) $end - (int) $start,
                'type' => $type,
                'notes' => $notes !== [] ? implode("\n", $notes) : null,
            ],
            'skip_reason' => null,
        ];
    }

    /**
     * Whether the instructor already has a trip on that date with the same
     * odometer readings (e.g. logged in Drive since the import).
     *
     * @param  array{date: string, start_mileage: int, end_mileage: int}  $data
     */
    public function instructorHasTrip(int $instructorId, array $data): bool
    {
        return MileageLog::query()
            ->where('instructor_id', $instructorId)
            ->whereDate('date', $data['date'])
            ->where('start_mileage', $data['start_mileage'])
            ->where('end_mileage', $data['end_mileage'])
            ->exists();
    }

    /**
     * Create the trip (no vehicle) and map its mileage_ref.
     *
     * @param  array{date: string, start_mileage: int, end_mileage: int, miles: int, type: string, notes: string|null}  $data  From parse()
     */
    public function __invoke(int $instructorId, string $mileageRef, array $data, ImportRun $run): MileageLog
    {
        $log = MileageLog::create($data + ['instructor_id' => $instructorId, 'vehicle_id' => null]);

        ImportMapping::record($run, ImportMapping::ENTITY_MILEAGE, $mileageRef, $log->id);

        return $log;
    }
}
