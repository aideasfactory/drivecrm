<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Support\ImportValues;

class ImportStagedDiaryAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected ImportDiaryItemAction $importDiaryItem,
        protected CreateImportedPracticalTestSlotAction $createImportedPracticalTestSlot,
        protected ConvertImportedLessonToPracticalTestAction $convertImportedLessonToPracticalTest,
        protected FillImportedLessonSummaryAction $fillImportedLessonSummary,
        protected ResolveImportedOrderAction $resolveImportedOrder,
        protected SyncImportedOrderAction $syncImportedOrder,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
    ) {}

    /**
     * Stage 3 for one instructor ref: their diary, oldest first (so each
     * pupil's lesson numbers run in date order). The agreed lesson rules:
     *
     * - Already imported → never changed (only an empty summary is filled).
     * - Cancelled, or a bad date/time (blank, before 2000, end not after
     *   start) → skipped.
     * - lesson / mock_test with a pupil → a lesson on the pupil's £0 imported
     *   order, only when the pupil is with this instructor and has no lesson
     *   at that date + time. Before today → completed, today onwards →
     *   booked, whatever the row says (no sign-off, payment, payout or email).
     *   A mock test's summary starts "Mock test".
     * - driving_test with a pupil → a practical test slot (no lesson), one
     *   per pupil per day.
     * - blocked / holiday / no pupil → an unavailable block.
     *
     * @return array{counts: array<string, int>, dates: array<string, true>}
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = [
            'lessons' => 0, 'mock_tests' => 0, 'practical_tests' => 0, 'diary_blocks' => 0,
            'already_imported' => 0, 'summaries_filled' => 0,
            'skipped_cancelled' => 0, 'skipped_bad_date' => 0, 'skipped_pupil_not_imported' => 0,
            'skipped_pupil_with_other_instructor' => 0, 'skipped_pupil_has_lesson_then' => 0, 'skipped_test_same_day' => 0,
        ];
        $dates = [];
        $instructor = ($this->findImportedInstructor)($instructorRef);

        if ($instructor === null) {
            return ['counts' => $counts, 'dates' => $dates];
        }

        $rows = array_column($this->readStagedRows->all($run, 'diary', $instructorRef), 'row');
        usort($rows, fn (array $a, array $b) => [ImportValues::date($a['date'])?->toDateString(), ImportValues::time($a['start_time'])]
            <=> [ImportValues::date($b['date'])?->toDateString(), ImportValues::time($b['start_time'])]);

        $mappedDiary = ImportMapping::modelIdsFor(ImportMapping::ENTITY_DIARY, array_column($rows, 'diary_ref'));
        $studentIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_STUDENT, array_filter(array_column($rows, 'student_ref')));
        $today = now()->toDateString();

        /** @var array<int, Student> $students */
        $students = [];
        /** @var array<int, Order> $orders */
        $orders = [];
        /** @var array<int, int> $nextLessonNumbers */
        $nextLessonNumbers = [];

        foreach ($rows as $row) {
            $ref = (string) $row['diary_ref'];
            $studentRef = (string) ($row['student_ref'] ?? '');

            if (isset($mappedDiary[$ref])) {
                $counts['already_imported']++;
                $counts['summaries_filled'] += (int) ($this->fillImportedLessonSummary)($row);

                continue;
            }

            if (($row['status'] ?? '') === 'cancelled') {
                $counts['skipped_cancelled']++;

                continue;
            }

            $date = ImportValues::date((string) $row['date']);
            $start = ImportValues::time((string) $row['start_time']);
            $end = ImportValues::time((string) $row['end_time']);

            if ($date === null || $date->year < 2000 || $start === null || $end === null || $end <= $start) {
                $counts['skipped_bad_date']++;

                continue;
            }

            $type = ($row['item_type'] ?? '') !== '' ? $row['item_type'] : ($studentRef !== '' ? 'lesson' : 'blocked');
            $student = null;

            if ($studentRef !== '' && in_array($type, ['lesson', 'mock_test', 'driving_test'], true)) {
                $studentId = $studentIds[$studentRef] ?? null;
                $student = $studentId !== null ? ($students[$studentId] ??= Student::query()->find($studentId)) : null;

                if ($student === null) {
                    $counts['skipped_pupil_not_imported']++;

                    continue;
                }

                if ((int) $student->instructor_id !== $instructor->id) {
                    $counts['skipped_pupil_with_other_instructor']++;

                    continue;
                }
            }

            $dateString = $date->toDateString();

            if ($type === 'driving_test' && $student !== null) {
                if ($this->convertImportedLessonToPracticalTest->pupilHasTestOn($student->id, $dateString)) {
                    $counts['skipped_test_same_day']++;

                    continue;
                }

                ($this->createImportedPracticalTestSlot)($instructor, $student, $ref, $dateString, $start, $end, $row['notes'] ?? null, $run);
                $counts['practical_tests']++;
                $dates[$dateString] = true;

                continue;
            }

            if (in_array($type, ['lesson', 'mock_test'], true) && $student !== null) {
                $row['status'] = $dateString < $today ? 'completed' : 'booked';

                if ($type === 'mock_test') {
                    $notes = trim((string) ($row['summary'] ?? '')) ?: trim((string) ($row['notes'] ?? ''));
                    $row['summary'] = 'Mock test'.($notes !== '' ? ': '.$notes : '');
                }

                if ($this->importDiaryItem->studentHasLessonAt($student->id, $row)) {
                    $counts['skipped_pupil_has_lesson_then']++;

                    continue;
                }

                $order = $orders[$student->id] ??= ($this->resolveImportedOrder)($instructor, $student);
                $nextLessonNumbers[$student->id] ??= (int) Lesson::query()
                    ->whereHas('order', fn ($query) => $query->where('student_id', $student->id))
                    ->max('student_lesson_number') + 1;

                if (($this->importDiaryItem)($instructor, $row, $order, $nextLessonNumbers[$student->id], $run) !== null) {
                    $nextLessonNumbers[$student->id]++;
                    $counts[$type === 'mock_test' ? 'mock_tests' : 'lessons']++;
                    $dates[$dateString] = true;
                }

                continue;
            }

            if ($type === 'holiday' && trim((string) ($row['notes'] ?? '')) === '') {
                $row['notes'] = 'Holiday';
            }

            if (($this->importDiaryItem)($instructor, $row, null, 0, $run) !== null) {
                $counts['diary_blocks']++;
                $dates[$dateString] = true;
            }
        }

        foreach ($orders as $order) {
            ($this->syncImportedOrder)($order);
            ($this->recalculateStudentLessonNumbers)($order->student_id);
        }

        return ['counts' => $counts, 'dates' => $dates];
    }
}
