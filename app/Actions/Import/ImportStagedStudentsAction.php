<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Student;
use App\Support\ImportValues;

class ImportStagedStudentsAction
{
    /**
     * students.csv columns that tick a checklist item: column => [item key, date column].
     */
    protected const CHECKLIST = [
        'theory_passed' => ['sit_theory_test', 'theory_pass_date'],
        'practical_passed' => ['sit_practical_test', 'practical_pass_date'],
        'eyesight_checked' => ['eyesight_checked', 'eyesight_checked_date'],
    ];

    /** Full UK postcode at the end of a pick-up point, e.g. "LS14 6RX". */
    protected const FULL_POSTCODE = '/^([A-Z]{1,2}[0-9][A-Z0-9]?)\s*([0-9][A-Z]{2})$/';

    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected ImportStudentAction $importStudent,
        protected FillBlankAttributesAction $fillBlankAttributes,
        protected TickImportedChecklistItemAction $tickImportedChecklistItem,
        protected CreateImportedPickupPointsAction $createImportedPickupPoints,
    ) {}

    /**
     * Stage 2 for one instructor ref: their pupils, each pupil's details and
     * checklist, then pick-up points.
     *
     * - New ref → created (blank/generated email → placeholder, never emailed).
     * - Imported before → date of birth, phone and contact details filled only
     *   where blank. A Drive+ pupil linked by email is never changed.
     * - Checklist: theory/practical passed and eyesight checked ("yes") tick
     *   the item with its date; a licence number ticks "Driving licence number"
     *   with the number. Only ever ticks — an item already ticked is untouched.
     * - Pick-up points only for pupils who have none in Drive; rows without a
     *   full postcode are skipped; the primary one is the default.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = [
            'students_created' => 0, 'students_existing' => 0, 'students_linked' => 0, 'students_skipped' => 0,
            'student_fields_filled' => 0, 'checklist_ticked' => 0,
            'pickup_points_added' => 0, 'pickup_points_skipped' => 0, 'pupils_already_with_points' => 0,
        ];

        $instructor = ($this->findImportedInstructor)($instructorRef);

        if ($instructor === null) {
            return $counts;
        }

        foreach (($this->readStagedRows)($run, 'students', $instructorRef) as ['row' => $row]) {
            $result = ($this->importStudent)($instructor, $row, $run);
            $student = $result['student'];

            if ($student === null) {
                $counts['students_skipped']++;

                continue;
            }

            if ($result['linked']) {
                $counts['students_linked']++;

                continue;
            }

            $counts[$result['created'] ? 'students_created' : 'students_existing']++;

            if (! $result['created'] && ! ImportMapping::wasImported(ImportMapping::ENTITY_STUDENT, $student->id)) {
                continue;
            }

            $counts['student_fields_filled'] += count(($this->fillBlankAttributes)($student, [
                'date_of_birth' => $this->dateOfBirth($row['date_of_birth'] ?? ''),
                'phone' => ImportValues::ukPhone($row['phone'] ?? null),
                'contact_first_name' => trim((string) ($row['contact_first_name'] ?? '')),
                'contact_surname' => trim((string) ($row['contact_surname'] ?? '')),
                'contact_email' => filter_var($row['contact_email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower($row['contact_email']) : null,
                'contact_phone' => ImportValues::ukPhone($row['contact_phone'] ?? null),
            ]));

            $counts['checklist_ticked'] += $this->tickChecklist($student, $row);
        }

        $this->importPickupPoints($run, $instructorRef, $counts);

        return $counts;
    }

    /**
     * @param  array<string, string>  $row
     * @return int Items ticked
     */
    protected function tickChecklist(Student $student, array $row): int
    {
        $ticked = 0;

        foreach (self::CHECKLIST as $column => [$key, $dateColumn]) {
            if (strtolower(trim((string) ($row[$column] ?? ''))) !== 'yes') {
                continue;
            }

            $date = $this->pastDate($row[$dateColumn] ?? '');
            $ticked += (int) ($this->tickImportedChecklistItem)($student, $key, false, $date);
        }

        $licence = strtoupper(trim((string) ($row['driving_licence_number'] ?? '')));

        if ($licence !== '') {
            $ticked += (int) ($this->tickImportedChecklistItem)($student, 'driving_licence_number', false, null, $licence);
        }

        return $ticked;
    }

    /**
     * @param  array<string, int>  $counts
     */
    protected function importPickupPoints(ImportRun $run, string $instructorRef, array &$counts): void
    {
        $byStudent = [];

        foreach (($this->readStagedRows)($run, 'pickup_points', $instructorRef) as ['row' => $row]) {
            $byStudent[(string) $row['student_ref']][] = $row;
        }

        $studentIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_STUDENT, array_keys($byStudent));

        foreach ($byStudent as $studentRef => $rows) {
            $student = isset($studentIds[$studentRef]) ? Student::query()->find($studentIds[$studentRef]) : null;

            // Not imported, or deleted in Drive since: left alone.
            if ($student === null) {
                $counts['pickup_points_skipped'] += count($rows);

                continue;
            }

            $studentId = $student->id;

            if ($student->pickupPoints()->exists()) {
                $counts['pupils_already_with_points']++;

                continue;
            }

            usort($rows, fn (array $a, array $b) => (($b['is_primary'] ?? '') === 'yes') <=> (($a['is_primary'] ?? '') === 'yes'));

            $clean = [];

            foreach ($rows as $row) {
                $postcode = strtoupper(trim((string) $row['postcode']));

                if (! preg_match(self::FULL_POSTCODE, $postcode, $parts)) {
                    $counts['pickup_points_skipped']++;

                    continue;
                }

                $label = trim((string) ($row['label'] ?? ''));
                $address = trim((string) $row['address']);

                $clean[] = [
                    'label' => $label !== '' ? $label : null,
                    'address' => $address !== '' ? $address : "{$parts[1]} {$parts[2]}",
                    'postcode' => "{$parts[1]} {$parts[2]}",
                ];
            }

            if ($clean !== [] && ($this->createImportedPickupPoints)($studentId, CreateImportedPickupPointsAction::plan($clean))) {
                $counts['pickup_points_added'] += count($clean);
            }
        }
    }

    /**
     * A plausible learner's date of birth (Y-m-d: aged 15–100 today), or null.
     */
    protected function dateOfBirth(string $value): ?string
    {
        $date = ImportValues::date($value);

        return $date !== null && $date->lessThanOrEqualTo(now()->subYears(15)) && $date->greaterThan(now()->subYears(100))
            ? $date->toDateString()
            : null;
    }

    /**
     * A date in the past (Y-m-d), or null when blank, unreadable or not past.
     */
    protected function pastDate(string $value): ?string
    {
        $date = ImportValues::date($value);

        return $date !== null && $date->isPast() && $date->year >= 1900 ? $date->toDateString() : null;
    }
}
