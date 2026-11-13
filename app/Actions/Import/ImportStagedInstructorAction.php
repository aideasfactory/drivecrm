<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Support\ImportValues;

class ImportStagedInstructorAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected ImportInstructorAction $importInstructor,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected FillBlankAttributesAction $fillBlankAttributes,
        protected ImportCoverageAction $importCoverage,
    ) {}

    /**
     * Stage 1 for one instructor ref: the instructor, then their coverage.
     *
     * - New ref → created as in the file (status included; no email sent).
     * - Imported before → phone, address, postcode and bio filled only where
     *   blank. A Drive instructor linked by email is never changed.
     * - Coverage: missing sectors added. E14 is dropped unless the
     *   instructor's own postcode is in E14 (the legacy system added it to
     *   hundreds of instructors by default).
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['instructors_created' => 0, 'instructors_existing' => 0, 'instructor_fields_filled' => 0, 'coverage_added' => 0, 'coverage_e14_dropped' => 0];
        $row = $this->readStagedRows->all($run, 'instructors', $instructorRef)[0]['row'] ?? null;

        if ($row !== null && ImportValues::driveInstructorId($instructorRef) === null) {
            $result = ($this->importInstructor)($row, $run);
            $instructor = $result['instructor'];
            $counts[$result['created'] ? 'instructors_created' : 'instructors_existing']++;

            if (! $result['created'] && ImportMapping::wasImported(ImportMapping::ENTITY_INSTRUCTOR, $instructor->id)) {
                $postcode = trim((string) ($row['postcode'] ?? ''));

                $counts['instructor_fields_filled'] += count(($this->fillBlankAttributes)($instructor, [
                    'phone' => ImportValues::ukPhone($row['phone'] ?? null),
                    'address' => trim((string) ($row['address'] ?? '')),
                    'postcode' => $postcode !== '' ? strtoupper($postcode) : null,
                    'bio' => trim((string) ($row['bio'] ?? '')),
                ]));
            }
        } else {
            $instructor = ($this->findImportedInstructor)($instructorRef);
        }

        if ($instructor === null) {
            return $counts;
        }

        $basedInE14 = str_starts_with(strtoupper(str_replace(' ', '', (string) $instructor->postcode)), 'E14');
        $sectors = [];

        foreach (($this->readStagedRows)($run, 'coverage', $instructorRef) as ['row' => $coverage]) {
            foreach (ImportValues::postcodeSectors((string) $coverage['postcode_sector']) ?? [] as $sector) {
                if ($sector === 'E14' && ! $basedInE14) {
                    $counts['coverage_e14_dropped']++;

                    continue;
                }

                $sectors[] = $sector;
            }
        }

        $counts['coverage_added'] = ($this->importCoverage)($instructor, $instructorRef, $sectors, $run);

        return $counts;
    }
}
