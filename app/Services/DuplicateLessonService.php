<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\SyncImportedOrderAction;
use App\Actions\Lesson\FindDuplicateLessonsAction;
use App\Actions\Lesson\RemoveDuplicateLessonAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\PaymentMode;
use App\Models\Lesson;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DuplicateLessonService extends BaseService
{
    public const CSV_COLUMNS = [
        'group_id', 'group_types', 'instructor_id', 'instructor_name', 'pupil_id', 'pupil_name',
        'lesson_id', 'order_id', 'order_payment_mode', 'date', 'start_time', 'end_time', 'status',
        'goroadie_ids', 'imported', 'import_run_ids', 'created_at', 'updated_at', 'drive_plus_changes',
        'lesson_payment_ids', 'lesson_payment_statuses', 'stripe_invoice_ids', 'stripe_charge_ids',
        'payout_id', 'stripe_transfer_id', 'order_stripe_ids', 'finance_ids', 'reflective_log_id',
        'calendar_item_id', 'decision', 'keep_lesson_id', 'reason', 'needs_manual_review', 'review_reasons',
    ];

    /**
     * The columns lessons:remove-duplicates reads back from a reviewed CSV.
     */
    protected const REQUIRED_COLUMNS = [
        'group_id', 'lesson_id', 'order_id', 'date', 'start_time', 'status', 'decision', 'keep_lesson_id', 'needs_manual_review',
    ];

    public function __construct(
        protected FindDuplicateLessonsAction $findDuplicateLessons,
        protected RemoveDuplicateLessonAction $removeDuplicateLesson,
        protected SyncImportedOrderAction $syncImportedOrder,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected InstructorCalendarService $instructorCalendarService,
    ) {}

    /**
     * Scan every instructor for duplicate lessons and write the review CSV
     * (lessons:find-duplicates). Read-only apart from the CSV file.
     *
     * @return array{path: string, counts: array<string, int>, groups: array<int, array<string, mixed>>}
     */
    public function findDuplicates(?string $csvPath = null): array
    {
        $result = ($this->findDuplicateLessons)();
        $path = $csvPath ?? storage_path('app/duplicate-lessons/duplicate-lessons-'.now()->format('Ymd-His').'.csv');

        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Can't write {$path}.");
        }

        fputcsv($handle, self::CSV_COLUMNS);

        foreach ($result['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                fputcsv($handle, array_map(fn (string $column) => $row[$column] ?? '', self::CSV_COLUMNS));
            }
        }

        fclose($handle);

        return ['path' => $path, 'counts' => $result['counts'], 'groups' => $result['groups']];
    }

    /**
     * Act on the REMOVE rows of a reviewed CSV (lessons:remove-duplicates).
     * Dry run unless $confirm. Groups flagged for manual review, or whose rows
     * don't name exactly one KEEP lesson, are skipped. Each group runs in its
     * own transaction and is rolled back whole if any of its lessons is
     * refused. Every change is appended to a JSON-lines audit file.
     *
     * @param  array<int, string>  $onlyGroupIds
     * @return array{counts: array<string, int>, lessons: array<int, array{group_id: string, lesson_id: int|null, keep_lesson_id: int|null, outcome: string}>, audit_path: string|null}
     */
    public function removeDuplicates(string $csvPath, bool $confirm, array $onlyGroupIds = []): array
    {
        $groups = $this->readReviewedCsv($csvPath);
        $dryRun = ! $confirm;
        $auditPath = $dryRun ? null : storage_path('logs/lessons-remove-duplicates-'.now()->format('Ymd-His').'.jsonl');
        $counts = ['groups' => 0, 'groups_done' => 0, 'groups_skipped' => 0, 'lessons_removed' => 0, 'lessons_skipped' => 0];
        $lessons = [];

        foreach ($groups as $groupId => $rows) {
            if ($onlyGroupIds !== [] && ! in_array($groupId, $onlyGroupIds, true)) {
                continue;
            }

            $counts['groups']++;
            $removeRows = array_values(array_filter($rows, fn (array $row): bool => $row['decision'] === FindDuplicateLessonsAction::DECISION_REMOVE));
            $problem = $this->groupProblem($rows);

            if ($problem !== null) {
                $counts['groups_skipped']++;
                $counts['lessons_skipped'] += count($removeRows);
                $lessons[] = ['group_id' => $groupId, 'lesson_id' => null, 'keep_lesson_id' => null, 'outcome' => 'group skipped: '.$problem];
                $this->audit($auditPath, ['group_id' => $groupId, 'outcome' => 'group skipped: '.$problem]);

                continue;
            }

            $keepLessonId = (int) array_values(array_filter($rows, fn (array $row): bool => $row['decision'] === FindDuplicateLessonsAction::DECISION_KEEP))[0]['lesson_id'];
            $outcomes = $dryRun
                ? $this->runGroup($groupId, $keepLessonId, $removeRows, true)
                : $this->runGroupInTransaction($groupId, $keepLessonId, $removeRows, $auditPath);

            $groupRemoved = ! in_array(false, array_map(fn (array $outcome): bool => in_array($outcome['outcome'], ['removed', 'would remove'], true), $outcomes), true);
            $counts[$groupRemoved ? 'groups_done' : 'groups_skipped']++;

            foreach ($outcomes as $outcome) {
                $counts[$groupRemoved ? 'lessons_removed' : 'lessons_skipped']++;
                $lessons[] = [
                    'group_id' => $groupId,
                    'lesson_id' => $outcome['lesson_id'],
                    'keep_lesson_id' => $keepLessonId,
                    'outcome' => $groupRemoved || ! in_array($outcome['outcome'], ['removed', 'would remove'], true)
                        ? $outcome['outcome']
                        : ($dryRun ? 'would be skipped with its group' : 'rolled back with its group'),
                ];
            }
        }

        return ['counts' => $counts, 'lessons' => $lessons, 'audit_path' => $auditPath];
    }

    /**
     * @param  array<int, array<string, string>>  $removeRows
     * @return array<int, array{lesson_id: int, outcome: string}>
     */
    protected function runGroupInTransaction(string $groupId, int $keepLessonId, array $removeRows, string $auditPath): array
    {
        $audit = [];

        DB::beginTransaction();

        try {
            $outcomes = $this->runGroup($groupId, $keepLessonId, $removeRows, false, $audit);

            if (in_array(false, array_map(fn (array $outcome): bool => $outcome['outcome'] === 'removed', $outcomes), true)) {
                DB::rollBack();
                $this->audit($auditPath, [
                    'at' => now()->toIso8601String(),
                    'group_id' => $groupId,
                    'keep_lesson_id' => $keepLessonId,
                    'outcome' => 'group rolled back — nothing changed',
                    'lessons' => $outcomes,
                ]);

                return $outcomes;
            }

            $touchedLessons = $this->tidyAfterRemoval($keepLessonId, array_column($outcomes, 'lesson_id'));

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->audit($auditPath, [
                'at' => now()->toIso8601String(),
                'group_id' => $groupId,
                'keep_lesson_id' => $keepLessonId,
                'outcome' => 'group rolled back after an error — nothing changed',
                'error' => $exception->getMessage(),
            ]);

            return array_map(fn (array $row): array => ['lesson_id' => (int) $row['lesson_id'], 'outcome' => 'error: '.$exception->getMessage()], $removeRows);
        }

        foreach ($touchedLessons as $lesson) {
            if ($lesson->instructor_id !== null && $lesson->date !== null) {
                $this->instructorCalendarService->invalidateCalendarCache((int) $lesson->instructor_id, $lesson->date->toDateString());
            }
        }

        foreach ($audit as $entry) {
            $this->audit($auditPath, $entry);
        }

        Log::info('lessons:remove-duplicates removed duplicate lessons', [
            'group_id' => $groupId,
            'keep_lesson_id' => $keepLessonId,
            'removed_lesson_ids' => array_column($outcomes, 'lesson_id'),
            'audit_file' => $auditPath,
        ]);

        return $outcomes;
    }

    /**
     * @param  array<int, array<string, string>>  $removeRows
     * @param  array<int, array<string, mixed>>  $audit
     * @return array<int, array{lesson_id: int, outcome: string}>
     */
    protected function runGroup(string $groupId, int $keepLessonId, array $removeRows, bool $dryRun, array &$audit = []): array
    {
        $outcomes = [];

        foreach ($removeRows as $row) {
            $result = ($this->removeDuplicateLesson)(
                (int) $row['lesson_id'],
                $keepLessonId,
                ['status' => $row['status'], 'date' => $row['date'], 'start_time' => $row['start_time'], 'order_id' => $row['order_id']],
                $groupId,
                $dryRun,
            );

            $outcomes[] = ['lesson_id' => (int) $row['lesson_id'], 'outcome' => $result['outcome']];
            $audit[] = [
                'at' => now()->toIso8601String(),
                'group_id' => $groupId,
                'lesson_id' => (int) $row['lesson_id'],
                'keep_lesson_id' => $keepLessonId,
                'outcome' => $result['outcome'],
                'changes' => $result['changes'],
                'before' => $result['before'],
                'csv_row' => $row,
            ];
        }

        return $outcomes;
    }

    /**
     * Re-sync the imported orders the duplicates were on and renumber the
     * pupil's open lessons. Returns the lessons touched, for cache clearing.
     *
     * @param  array<int, int>  $removedLessonIds
     * @return Collection<int, Lesson>
     */
    protected function tidyAfterRemoval(int $keepLessonId, array $removedLessonIds): Collection
    {
        $lessons = Lesson::query()->whereKey([$keepLessonId, ...$removedLessonIds])->with('order')->get();

        $lessons->pluck('order')
            ->filter(fn (?Order $order): bool => $order?->payment_mode === PaymentMode::IMPORTED)
            ->unique('id')
            ->each(fn (Order $order) => ($this->syncImportedOrder)($order));

        foreach ($lessons->map(fn (Lesson $lesson) => $lesson->order?->student_id)->filter()->unique() as $studentId) {
            ($this->recalculateStudentLessonNumbers)((int) $studentId);
        }

        return $lessons;
    }

    /**
     * Why a group can't be acted on, or null when it can.
     *
     * @param  array<int, array<string, string>>  $rows
     */
    protected function groupProblem(array $rows): ?string
    {
        if (array_filter($rows, fn (array $row): bool => $row['needs_manual_review'] !== 'no') !== []) {
            return 'flagged for manual review';
        }

        $decisions = array_column($rows, 'decision');

        if (array_diff($decisions, [FindDuplicateLessonsAction::DECISION_KEEP, FindDuplicateLessonsAction::DECISION_REMOVE]) !== []) {
            return 'decision must be KEEP or REMOVE on every row';
        }

        $keepRows = array_values(array_filter($rows, fn (array $row): bool => $row['decision'] === FindDuplicateLessonsAction::DECISION_KEEP));

        if (count($keepRows) !== 1) {
            return 'needs exactly one KEEP row';
        }

        if (! in_array(FindDuplicateLessonsAction::DECISION_REMOVE, $decisions, true)) {
            return 'no REMOVE rows';
        }

        foreach ($rows as $row) {
            if ((int) $row['keep_lesson_id'] !== (int) $keepRows[0]['lesson_id']) {
                return "keep_lesson_id on lesson {$row['lesson_id']} doesn't match the KEEP row";
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, array<string, string>>> group id => rows
     */
    protected function readReviewedCsv(string $path): array
    {
        if (! is_file($path) || ($handle = fopen($path, 'r')) === false) {
            throw new RuntimeException("Can't read {$path}.");
        }

        $header = array_map(fn ($column) => trim((string) $column, " \t\n\r\0\x0B\u{FEFF}"), fgetcsv($handle) ?: []);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            fclose($handle);

            throw new RuntimeException('The CSV is missing columns: '.implode(', ', $missing).'. Use the file written by lessons:find-duplicates.');
        }

        $groups = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || count($values) !== count($header)) {
                continue;
            }

            $row = array_map(fn ($value) => trim((string) $value), array_combine($header, $values));
            $row['decision'] = strtoupper($row['decision']);
            $row['needs_manual_review'] = strtolower($row['needs_manual_review']);

            if ($row['group_id'] !== '' && ctype_digit($row['lesson_id'])) {
                $groups[$row['group_id']][] = $row;
            }
        }

        fclose($handle);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function audit(?string $path, array $entry): void
    {
        if ($path === null) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::append($path, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }
}
