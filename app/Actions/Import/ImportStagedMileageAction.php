<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;

class ImportStagedMileageAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected ImportMileageLogAction $importMileageLog,
    ) {}

    /**
     * Stage 8 for one instructor ref: their mileage trips, by the agreed
     * rules (ImportMileageLogAction): clean rows only, no vehicles created,
     * registration kept in the notes, a trip the instructor already logged
     * is skipped. A registration that isn't one (e.g. "AUTOMATIC") is left out.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['trips_added' => 0, 'already_imported' => 0, 'skipped_not_clean' => 0, 'skipped_already_logged' => 0];
        $instructor = ($this->findImportedInstructor)($instructorRef);

        if ($instructor === null) {
            return $counts;
        }

        $rows = array_column($this->readStagedRows->all($run, 'mileage', $instructorRef), 'row');
        $mapped = ImportMapping::modelIdsFor(ImportMapping::ENTITY_MILEAGE, array_column($rows, 'mileage_ref'));

        foreach ($rows as $row) {
            if (isset($mapped[$row['mileage_ref']])) {
                $counts['already_imported']++;

                continue;
            }

            if (! CheckStagedImportAction::looksLikeRegistration((string) ($row['vehicle_reg'] ?? ''))) {
                $row['vehicle_reg'] = '';
            }

            $row['end_date'] ??= '';
            $parsed = ImportMileageLogAction::parse($row);

            if ($parsed['data'] === null) {
                $counts['skipped_not_clean']++;

                continue;
            }

            if ($this->importMileageLog->instructorHasTrip($instructor->id, $parsed['data'])) {
                $counts['skipped_already_logged']++;

                continue;
            }

            ($this->importMileageLog)($instructor->id, (string) $row['mileage_ref'], $parsed['data'], $run);
            $counts['trips_added']++;
        }

        return $counts;
    }
}
