<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\CalendarItemType;
use App\Enums\StudentTestResult;
use App\Enums\StudentTestType;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Student;
use App\Models\StudentTest;
use App\Support\ImportValues;

class ImportStagedTestsAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected TickImportedChecklistItemAction $tickImportedChecklistItem,
        protected SyncLatestPracticalTestDateAction $syncLatestPracticalTestDate,
    ) {}

    /**
     * Stage 4 for one instructor ref: theory and practical tests into
     * student_tests (one row per test_ref; never changed once imported).
     *
     * - A test dated after today marked `fail` is recorded as `booked` (it
     *   can't have been sat; `fail` is the legacy export's default).
     * - A practical test links to its diary slot when that was imported.
     * - A pass ticks "Sit theory test" / "Sit practical test" with the date,
     *   only if not already ticked. A practical test that isn't cancelled
     *   moves "Book practical test" to its date if that's later.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['tests' => 0, 'tests_future_fail_booked' => 0, 'already_imported' => 0, 'checklist_ticked' => 0, 'skipped' => 0];
        $instructor = ($this->findImportedInstructor)($instructorRef);
        $rows = array_column($this->readStagedRows->all($run, 'tests', $instructorRef), 'row');

        $mapped = ImportMapping::modelIdsFor(ImportMapping::ENTITY_TEST, array_column($rows, 'test_ref'));
        $studentIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_STUDENT, array_column($rows, 'student_ref'));
        $slotIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_DIARY, array_filter(array_column($rows, 'diary_ref')));
        $today = now()->toDateString();

        foreach ($rows as $row) {
            $ref = (string) $row['test_ref'];

            if (isset($mapped[$ref])) {
                $counts['already_imported']++;

                continue;
            }

            $type = StudentTestType::tryFrom(strtolower((string) $row['test_type']));
            $result = StudentTestResult::tryFrom(strtolower((string) $row['result']));
            $date = ImportValues::date((string) $row['date']);
            $studentId = $studentIds[(string) $row['student_ref']] ?? null;
            $student = $studentId !== null ? Student::query()->find($studentId) : null;

            if ($type === null || $result === null || $date === null || $student === null) {
                $counts['skipped']++;

                continue;
            }

            $dateString = $date->toDateString();
            $slot = isset($slotIds[$row['diary_ref'] ?? '']) ? CalendarItem::query()->find($slotIds[$row['diary_ref']]) : null;
            $slot = $slot?->item_type === CalendarItemType::PracticalTest ? $slot : null;

            if ($result === StudentTestResult::Fail && $dateString > $today) {
                $result = StudentTestResult::Booked;
                $counts['tests_future_fail_booked']++;
            }

            $test = StudentTest::create([
                'student_id' => $student->id,
                // A theory test is sat by the pupil alone: no instructor.
                'instructor_id' => $type === StudentTestType::Practical ? $instructor?->id : null,
                'calendar_item_id' => $slot?->id,
                'type' => $type,
                'date' => $dateString,
                'time' => ImportValues::time((string) ($row['time'] ?? '')),
                'test_centre' => trim((string) ($row['test_centre'] ?? '')) ?: null,
                'result' => $result,
                'driving_faults' => $this->count($row['driving_faults'] ?? ''),
                'serious_faults' => $this->count($row['serious_faults'] ?? ''),
                'dangerous_faults' => $this->count($row['dangerous_faults'] ?? ''),
                'notes' => trim((string) ($row['report_notes'] ?? '')) ?: null,
            ]);

            ImportMapping::record($run, ImportMapping::ENTITY_TEST, $ref, $test->id);
            $counts['tests']++;

            if ($result === StudentTestResult::Pass && $dateString <= $today) {
                $key = $type === StudentTestType::Theory ? 'sit_theory_test' : 'sit_practical_test';
                $counts['checklist_ticked'] += (int) ($this->tickImportedChecklistItem)($student, $key, false, $dateString);
            }

            if ($type === StudentTestType::Practical && $result !== StudentTestResult::Cancelled) {
                ($this->syncLatestPracticalTestDate)($student, $dateString);
            }
        }

        return $counts;
    }

    protected function count(string $value): ?int
    {
        $value = trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }
}
