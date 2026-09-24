<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\BuildImportTemplateAction;
use App\Actions\Import\GetPendingImportedUsersAction;
use App\Actions\Import\ImportCoverageAction;
use App\Actions\Import\ImportDiaryItemAction;
use App\Actions\Import\ImportFinanceAction;
use App\Actions\Import\ImportInstructorAction;
use App\Actions\Import\ImportStudentAction;
use App\Actions\Import\ReadImportBundleAction;
use App\Actions\Import\ResolveImportedOrderAction;
use App\Actions\Import\SendImportedWelcomeEmailAction;
use App\Actions\Import\SyncImportedOrderAction;
use App\Actions\Import\ValidateImportBundleAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Support\ImportValues;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImportService extends BaseService
{
    /** Counters reported per run (also stored on import_runs.totals). */
    protected const TOTAL_KEYS = [
        'instructors_created',
        'instructors_linked',
        'locations',
        'students',
        'lessons',
        'diary_blocks',
        'finances',
        'receipts',
    ];

    public function __construct(
        protected ReadImportBundleAction $readImportBundle,
        protected ValidateImportBundleAction $validateImportBundle,
        protected ImportInstructorAction $importInstructor,
        protected ImportCoverageAction $importCoverage,
        protected ImportStudentAction $importStudent,
        protected ResolveImportedOrderAction $resolveImportedOrder,
        protected ImportDiaryItemAction $importDiaryItem,
        protected SyncImportedOrderAction $syncImportedOrder,
        protected ImportFinanceAction $importFinance,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected InstructorCalendarService $instructorCalendarService,
        protected GetPendingImportedUsersAction $getPendingImportedUsers,
        protected SendImportedWelcomeEmailAction $sendImportedWelcomeEmail,
        protected BuildImportTemplateAction $buildImportTemplate,
    ) {}

    /**
     * Build the downloadable template zip (spec + example CSVs).
     *
     * @return string Path to a temporary zip
     */
    public function buildTemplate(): string
    {
        return ($this->buildImportTemplate)();
    }

    /**
     * Read and validate a bundle zip in one step, without writing anything.
     *
     * `row_counts` has one entry per CSV plus `receipts` — how many rows
     * reference a receipt file.
     *
     * @return array{bundle: array<string, array<int|string, mixed>>, row_counts: array<string, int>, errors: array<int, array{file: string, line: int, message: string}>}
     */
    public function checkBundle(string $zipPath): array
    {
        $bundle = $this->readBundle($zipPath);

        $rowCounts = [];
        foreach (array_keys(ReadImportBundleAction::FILES) as $section) {
            $rowCounts[$section] = count($bundle[$section]);
        }
        $rowCounts['receipts'] = count(array_filter(
            $bundle['finances'],
            fn (array $row) => trim((string) ($row['receipt_file'] ?? '')) !== '',
        ));

        return [
            'bundle' => $bundle,
            'row_counts' => $rowCounts,
            'errors' => $this->validateBundle($bundle),
        ];
    }

    /**
     * Read a bundle zip into rows per section.
     *
     * @return array<string, array<int, array<string, string|int>>>
     */
    public function readBundle(string $zipPath): array
    {
        return ($this->readImportBundle)($zipPath);
    }

    /**
     * Validate a bundle without writing anything.
     *
     * @param  array<string, array<int, array<string, string|int>>>  $bundle
     * @return array<int, array{file: string, line: int, message: string}>
     */
    public function validateBundle(array $bundle): array
    {
        return ($this->validateImportBundle)($bundle);
    }

    /**
     * Import a validated bundle, one instructor per transaction, in dependency
     * order: instructor → coverage → students → diary → finances (+ receipts).
     * Rows already imported on an earlier run are skipped.
     *
     * The run is recorded in import_runs (who, where from, which file, totals,
     * outcome) and every row it creates is linked to it via import_mappings.
     * If an instructor fails, earlier instructors stay imported, the run is
     * marked failed and the exception is rethrown — re-running picks up the rest.
     *
     * @param  array<string, array<int|string, mixed>>  $bundle  A bundle that passed validateBundle()
     * @param  string  $zipPath  The zip the bundle was read from (receipts are copied out of it)
     * @param  string  $source  ImportRun::SOURCE_UPLOAD (the Data Import page)
     * @param  (callable(string): void)|null  $progress  Receives one line per instructor
     * @return array{run: ImportRun, totals: array<string, int>}
     */
    public function importBundle(
        array $bundle,
        string $zipPath,
        string $source,
        ?int $userId = null,
        ?string $fileName = null,
        ?callable $progress = null,
    ): array {
        $run = ImportRun::create([
            'user_id' => $userId,
            'source' => $source,
            'file_name' => $fileName,
            'status' => ImportRun::STATUS_RUNNING,
        ]);

        $totals = array_fill_keys(self::TOTAL_KEYS, 0);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open zip: {$zipPath}");
        }

        try {
            foreach ($this->groupByInstructor($bundle) as $instructorRef => $sections) {
                $counts = DB::transaction(fn () => $this->importInstructorSections(
                    $instructorRef,
                    $sections,
                    $run,
                    $zip,
                    $bundle['attachments'] ?? [],
                ));

                foreach ($counts as $key => $count) {
                    $totals[$key] += $count;
                }

                if ($progress) {
                    $progress(sprintf(
                        '[%s] %d student(s), %d lesson(s), %d block(s), %d location(s), %d finance row(s), %d receipt(s).',
                        $instructorRef,
                        $counts['students'],
                        $counts['lessons'],
                        $counts['diary_blocks'],
                        $counts['locations'],
                        $counts['finances'],
                        $counts['receipts'],
                    ));
                }
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'totals' => $totals,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        } finally {
            $zip->close();
        }

        $run->update([
            'status' => ImportRun::STATUS_COMPLETED,
            'totals' => $totals,
            'completed_at' => now(),
        ]);

        return ['run' => $run, 'totals' => $totals];
    }

    /**
     * Imported users still waiting for their welcome email.
     *
     * @param  array<int, int>  $instructorIds  Limit to these instructors and their students (empty = everyone)
     * @param  string  $only  `all`, `instructors` or `students`
     * @return Collection<int, User>
     */
    public function getPendingWelcomeUsers(array $instructorIds = [], string $only = 'all'): Collection
    {
        return ($this->getPendingImportedUsers)($instructorIds, $only);
    }

    /**
     * Send the held welcome email to one imported user.
     */
    public function sendWelcomeEmail(User $user): bool
    {
        return ($this->sendImportedWelcomeEmail)($user);
    }

    /**
     * Resolve an instructor from a CRM instructor ID or an import instructor_ref.
     */
    public function resolveInstructorId(string $idOrRef): ?int
    {
        $mappedId = ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $idOrRef);

        if ($mappedId !== null) {
            return $mappedId;
        }

        return ctype_digit($idOrRef) && Instructor::query()->whereKey((int) $idOrRef)->exists()
            ? (int) $idOrRef
            : null;
    }

    /**
     * @param  array<string, array<int, array<string, string|int>>>  $sections
     * @param  array<string, array{name: string, size: int}|null>  $attachments
     * @return array<string, int>
     */
    protected function importInstructorSections(
        string $instructorRef,
        array $sections,
        ImportRun $run,
        ZipArchive $zip,
        array $attachments,
    ): array {
        $counts = array_fill_keys(self::TOTAL_KEYS, 0);

        $instructor = $this->resolveInstructor($instructorRef, $sections['instructors'][0] ?? null, $run, $counts);

        $counts['locations'] = ($this->importCoverage)(
            $instructor,
            $instructorRef,
            array_map(fn (array $row) => (string) $row['postcode_sector'], $sections['coverage']),
            $run,
        );

        foreach ($sections['students'] as $row) {
            $result = ($this->importStudent)($instructor, $row, $run);
            $counts['students'] += (int) $result['created'];
        }

        [$lessons, $blocks, $dates] = $this->importDiary($instructor, $sections['diary'], $run);
        $counts['lessons'] = $lessons;
        $counts['diary_blocks'] = $blocks;

        foreach ($sections['finances'] as $row) {
            $finance = ($this->importFinance)($instructor, $row, $run, $zip, $attachments);

            if ($finance) {
                $counts['finances']++;
                $counts['receipts'] += $finance->receipt_path ? 1 : 0;
            }
        }

        DB::afterCommit(function () use ($instructor, $dates): void {
            foreach (array_keys($dates) as $date) {
                $this->instructorCalendarService->invalidateCalendarCache($instructor->id, $date);
            }

            $this->invalidate($this->cacheKey('instructor', $instructor->id, 'grouped_students'));
        });

        return $counts;
    }

    /**
     * Import diary rows chronologically so each student's lesson numbers run
     * in date order, then settle each touched imported order.
     *
     * @param  array<int, array<string, string|int>>  $rows
     * @return array{0: int, 1: int, 2: array<string, true>} lessons, blocks, touched dates
     */
    protected function importDiary(Instructor $instructor, array $rows, ImportRun $run): array
    {
        usort($rows, fn (array $a, array $b) => [
            ImportValues::date((string) $a['date'])?->toDateString(),
            ImportValues::time((string) $a['start_time']),
        ] <=> [
            ImportValues::date((string) $b['date'])?->toDateString(),
            ImportValues::time((string) $b['start_time']),
        ]);

        $lessons = 0;
        $blocks = 0;
        $dates = [];

        /** @var array<int, Order> $orders */
        $orders = [];
        /** @var array<int, int> $nextLessonNumbers */
        $nextLessonNumbers = [];

        foreach ($rows as $row) {
            $studentRef = trim((string) ($row['student_ref'] ?? ''));
            $order = null;
            $lessonNumber = 0;

            if ($studentRef !== '') {
                $student = Student::query()->findOrFail(
                    ImportMapping::modelIdFor(ImportMapping::ENTITY_STUDENT, $studentRef)
                );

                $order = $orders[$student->id] ??= ($this->resolveImportedOrder)($instructor, $student);

                $nextLessonNumbers[$student->id] ??= (int) Lesson::query()
                    ->whereHas('order', fn ($query) => $query->where('student_id', $student->id))
                    ->max('student_lesson_number') + 1;

                $lessonNumber = $nextLessonNumbers[$student->id];
            }

            $item = ($this->importDiaryItem)($instructor, $row, $order, $lessonNumber, $run);

            if (! $item) {
                continue;
            }

            $dates[ImportValues::date((string) $row['date'])->toDateString()] = true;

            if ($order) {
                $lessons++;
                $nextLessonNumbers[$order->student_id]++;
            } else {
                $blocks++;
            }
        }

        foreach ($orders as $order) {
            ($this->syncImportedOrder)($order);
            ($this->recalculateStudentLessonNumbers)($order->student_id);
        }

        return [$lessons, $blocks, $dates];
    }

    /**
     * @param  array<string, string|int>|null  $row
     * @param  array<string, int>  $counts
     */
    protected function resolveInstructor(string $instructorRef, ?array $row, ImportRun $run, array &$counts): Instructor
    {
        if ($row === null) {
            return Instructor::query()->findOrFail(
                ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $instructorRef)
            );
        }

        $result = ($this->importInstructor)($row, $run);
        $counts[$result['created'] ? 'instructors_created' : 'instructors_linked']++;

        return $result['instructor'];
    }

    /**
     * Split every section by instructor_ref, keeping instructors in the order
     * they first appear (instructors.csv first).
     *
     * @param  array<string, array<int, array<string, string|int>>>  $bundle
     * @return array<string, array<string, array<int, array<string, string|int>>>>
     */
    protected function groupByInstructor(array $bundle): array
    {
        $grouped = [];

        foreach (array_keys(ReadImportBundleAction::FILES) as $section) {
            foreach ($bundle[$section] as $row) {
                $ref = (string) $row['instructor_ref'];

                $grouped[$ref] ??= array_fill_keys(array_keys(ReadImportBundleAction::FILES), []);
                $grouped[$ref][$section][] = $row;
            }
        }

        return $grouped;
    }
}
