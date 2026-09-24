<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Location;
use App\Support\ImportValues;

class ImportCoverageAction
{
    /**
     * Add postcode sectors to an instructor's coverage. Sectors the instructor
     * already covers are skipped, so re-running is safe. Each added sector is
     * recorded in import_mappings as "{instructor_ref}:{sector}".
     *
     * @param  array<int, string>  $sectors  Raw sector values from coverage.csv
     * @return int Number of locations inserted
     */
    public function __invoke(Instructor $instructor, string $instructorRef, array $sectors, ImportRun $run): int
    {
        $normalised = array_unique(array_filter(array_map(
            fn (string $sector) => ImportValues::postcodeSector($sector),
            $sectors,
        )));

        $existing = Location::query()
            ->where('instructor_id', $instructor->id)
            ->pluck('postcode_sector')
            ->all();

        $toInsert = array_diff($normalised, $existing);

        foreach ($toInsert as $sector) {
            $location = Location::create([
                'instructor_id' => $instructor->id,
                'postcode_sector' => $sector,
            ]);

            ImportMapping::record($run, ImportMapping::ENTITY_LOCATION, "{$instructorRef}:{$sector}", $location->id);
        }

        return count($toInsert);
    }
}
