<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\BuildImportTemplateAction;
use App\Actions\Import\CompleteImportedLessonAction;
use App\Actions\Import\CreateImportedPickupPointsAction;
use App\Actions\Import\ConvertImportedLessonToPracticalTestAction;
use App\Actions\Import\FillImportedLessonSummaryAction;
use App\Actions\Import\FixImportedPupilsAction;
use App\Actions\Import\GetPendingImportedUsersAction;
use App\Actions\Import\ImportCoverageAction;
use App\Actions\Import\ImportDiaryItemAction;
use App\Actions\Import\ImportFinanceAction;
use App\Actions\Import\ImportInstructorAction;
use App\Actions\Import\ImportMileageLogAction;
use App\Actions\Import\ImportStudentAction;
use App\Actions\Import\ParseImportedLocationsAction;
use App\Actions\Import\ReadCsvColumnsAction;
use App\Actions\Import\ReadDiaryCsvRowsAction;
use App\Actions\Import\ReadImportBundleAction;
use App\Actions\Import\ResolveImportedOrderAction;
use App\Actions\Import\SendImportedWelcomeEmailAction;
use App\Actions\Import\SyncImportedOrderAction;
use App\Actions\Import\SyncLatestPracticalTestDateAction;
use App\Actions\Import\TickImportedChecklistItemAction;
use App\Actions\Import\ValidateImportBundleAction;
use App\Actions\Instructor\UploadFinanceReceiptAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\CalendarItemType;
use App\Jobs\ImportBundleJob;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use App\Models\User;
use App\Support\ImportValues;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImportService extends BaseService
{
    /** Counters reported per run (also stored on import_runs.totals). */
    /**
     * The uploaded zip waits here for the queue worker, which may not share
     * the web server's disk. The bucket is public, so the name is random and
     * the zip is deleted as soon as the run ends.
     */
    protected const UPLOAD_DISK = 's3';

    protected const UPLOAD_DIRECTORY = 'imports';

    protected const TOTAL_KEYS = [
        'instructors_created',
        'instructors_linked',
        'locations',
        'students',
        'students_linked',
        'students_skipped',
        'lessons',
        'lessons_skipped',
        'lesson_summaries_filled',
        'lessons_already_in_diary',
        'lessons_marked_completed',
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
        protected FillImportedLessonSummaryAction $fillImportedLessonSummary,
        protected CompleteImportedLessonAction $completeImportedLesson,
        protected SyncImportedOrderAction $syncImportedOrder,
        protected ImportFinanceAction $importFinance,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected InstructorCalendarService $instructorCalendarService,
        protected FixImportedPupilsAction $fixImportedPupils,
        protected GetPendingImportedUsersAction $getPendingImportedUsers,
        protected SendImportedWelcomeEmailAction $sendImportedWelcomeEmail,
        protected BuildImportTemplateAction $buildImportTemplate,
        protected ReadDiaryCsvRowsAction $readDiaryCsvRows,
        protected ConvertImportedLessonToPracticalTestAction $convertImportedLessonToPracticalTest,
        protected SyncLatestPracticalTestDateAction $syncLatestPracticalTestDate,
        protected UploadFinanceReceiptAction $uploadFinanceReceipt,
        protected ReadCsvColumnsAction $readCsvColumns,
        protected ParseImportedLocationsAction $parseImportedLocations,
        protected CreateImportedPickupPointsAction $createImportedPickupPoints,
        protected TickImportedChecklistItemAction $tickImportedChecklistItem,
        protected ImportMileageLogAction $importMileageLog,
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
     * reference a receipt file. `report` lists every instructor and student
     * row with what the import will do with it (new / skipped and why).
     * `lesson_summaries_to_fill` counts lessons imported on an earlier run
     * whose empty summary this bundle's diary rows will fill;
     * `lessons_to_complete` those still pending that the bundle now marks
     * completed (see CompleteImportedLessonAction).
     *
     * @return array{bundle: array<string, array<int|string, mixed>>, row_counts: array<string, int>, errors: array<int, array{file: string, line: int, message: string}>, skipped: array<int, array{file: string, line: int, message: string}>, report: array<int, array{type: string, file: string, line: int, ref: string, name: string, email: string, instructor_ref: string, outcome: string}>, lesson_summaries_to_fill: int, lessons_to_complete: int}
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
            'skipped' => $this->validateImportBundle->skipped(),
            'report' => $this->validateImportBundle->report(),
            'lesson_summaries_to_fill' => $this->fillImportedLessonSummary->countFillable($bundle['diary']),
            'lessons_to_complete' => $this->completeImportedLesson->countCompletable($bundle['diary'], $this->existingInstructorIds($bundle['diary'])),
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
     * @param  ImportRun  $run  From queueImport()
     * @param  array<string, array<int|string, mixed>>  $bundle  A bundle that passed validateBundle()
     * @param  string  $zipPath  The zip the bundle was read from (receipts are copied out of it)
     * @param  (callable(string): void)|null  $progress  Receives one line per instructor
     * @return array{run: ImportRun, totals: array<string, int>}
     */
    public function importBundle(
        ImportRun $run,
        array $bundle,
        string $zipPath,
        ?callable $progress = null,
    ): array {
        $totals = array_fill_keys(self::TOTAL_KEYS, 0);
        $grouped = $this->groupByInstructor($bundle);

        $run->update([
            'status' => ImportRun::STATUS_RUNNING,
            'totals' => $totals + ['instructors_done' => 0, 'instructors_to_import' => count($grouped)],
        ]);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open zip: {$zipPath}");
        }

        try {
            $instructorsDone = 0;

            foreach ($grouped as $instructorRef => $sections) {
                if ($progress) {
                    $progress(sprintf(
                        '[%s] starting (%d of %d): %d student(s), %d diary row(s), %d finance row(s).',
                        $instructorRef,
                        $instructorsDone + 1,
                        count($grouped),
                        count($sections['students']),
                        count($sections['diary']),
                        count($sections['finances']),
                    ));
                }

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

                $instructorsDone++;

                // Progress for the Data Import page while the job runs.
                $run->update([
                    'totals' => $totals + ['instructors_done' => $instructorsDone, 'instructors_to_import' => count($grouped)],
                ]);

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
     * Store an uploaded bundle and queue it for import. The run starts as
     * `queued`; ImportBundleJob runs imports one at a time.
     */
    public function queueImport(UploadedFile $file, ?int $userId): ImportRun
    {
        $storedZipPath = $file->storeAs(
            self::UPLOAD_DIRECTORY,
            Str::random(40).'.zip',
            self::UPLOAD_DISK,
        );

        if ($storedZipPath === false) {
            throw new RuntimeException('Could not store the uploaded zip.');
        }

        $run = ImportRun::create([
            'user_id' => $userId,
            'source' => ImportRun::SOURCE_UPLOAD,
            'file_name' => $file->getClientOriginalName(),
            'status' => ImportRun::STATUS_QUEUED,
        ]);

        ImportBundleJob::dispatch($run, $storedZipPath);

        return $run;
    }

    /**
     * Run a queued import (called by ImportBundleJob). The bundle is checked
     * again first, since data may have changed since the page's check.
     *
     * A run still `running` was killed part-way (worker died); this attempt
     * carries on with it — rows already imported are skipped. A finished or
     * reset run is left alone.
     *
     * @throws Throwable When the import fails part-way (the run is marked failed)
     */
    public function runQueuedImport(ImportRun $run, string $storedZipPath): void
    {
        $run->refresh();

        if (! in_array($run->status, [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING], true)) {
            Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);

            return;
        }

        ini_set('memory_limit', '1024M');

        $localZipPath = tempnam(sys_get_temp_dir(), 'drivecrm-import-');

        try {
            $stream = Storage::disk(self::UPLOAD_DISK)->readStream($storedZipPath);

            if ($stream === null) {
                throw new RuntimeException('The uploaded zip is no longer available. Upload it again.');
            }

            file_put_contents($localZipPath, $stream);

            $result = $this->checkBundle($localZipPath);

            if ($result['errors'] !== []) {
                $run->update([
                    'status' => ImportRun::STATUS_FAILED,
                    'error' => count($result['errors']).' problem(s) found. Check the file again to see them. Nothing was imported.',
                    'completed_at' => now(),
                ]);

                return;
            }

            Log::info("Import run {$run->id}: starting.");

            $this->importBundle(
                $run,
                $result['bundle'],
                $localZipPath,
                fn (string $line) => Log::info("Import run {$run->id}: {$line}"),
            );

            Log::info("Import run {$run->id}: completed.");
        } catch (RuntimeException $exception) {
            $this->failQueuedImport($run, $storedZipPath, $exception);
        } finally {
            @unlink($localZipPath);
            Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);
        }
    }

    /**
     * Imports that are queued or running.
     *
     * @return Collection<int, ImportRun>
     */
    public function getActiveRuns(): Collection
    {
        return ImportRun::query()
            ->whereIn('status', [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING])
            ->orderBy('id')
            ->get();
    }

    /**
     * Unstick imports whose worker died: mark them failed and free the
     * one-at-a-time lock. A job still waiting for them then finds the run
     * finished and just deletes its zip.
     *
     * @return int Runs reset
     */
    public function resetActiveRuns(): int
    {
        $count = ImportRun::query()
            ->whereIn('status', [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING])
            ->update([
                'status' => ImportRun::STATUS_FAILED,
                'error' => 'Reset after the import stopped. Upload the zip again to carry on.',
                'completed_at' => now(),
            ]);

        ImportBundleJob::releaseOverlapLock();

        return $count;
    }

    /**
     * Mark a queued import failed (unless it already finished) and remove its zip.
     */
    public function failQueuedImport(ImportRun $run, string $storedZipPath, ?Throwable $exception): void
    {
        $run->refresh();

        if (in_array($run->status, [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING], true)) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'error' => $exception?->getMessage() ?? 'The import stopped before it finished.',
                'completed_at' => now(),
            ]);
        }

        Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);
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
     * Instructor IDs for the given emails; emails that aren't an instructor are left out.
     *
     * @param  array<int, string>  $emails
     * @return array<string, int> lowercased email => instructor ID
     */
    public function getInstructorIdsByEmail(array $emails): array
    {
        return Instructor::query()
            ->join('users', 'users.id', '=', 'instructors.user_id')
            ->whereIn('users.email', $emails)
            ->pluck('instructors.id', 'users.email')
            ->mapWithKeys(fn (int $id, string $email) => [strtolower($email) => $id])
            ->all();
    }

    /**
     * Split a list of emails into instructors still waiting for their welcome
     * email, instructors already emailed, and emails that aren't an instructor.
     *
     * @param  array<int, string>  $emails  Lowercased
     * @return array{pending: Collection<int, User>, already_sent: array<int, string>, not_instructors: array<int, string>}
     */
    public function previewInstructorWelcomeEmails(array $emails): array
    {
        $found = $this->getInstructorIdsByEmail($emails);

        $pending = $found === []
            ? new Collection
            : $this->getPendingWelcomeUsers(array_values($found), 'instructors');

        $pendingEmails = $pending->map(fn (User $user) => strtolower($user->email))->all();

        return [
            'pending' => $pending,
            'already_sent' => array_values(array_diff(array_keys($found), $pendingEmails)),
            'not_instructors' => array_values(array_diff($emails, array_keys($found))),
        ];
    }

    /**
     * One-off clean-up of imported pupils (see FixImportedPupilsAction).
     *
     * @param  int|null  $limit  Stop after this many pupils needing a change
     * @return array{pupils: int, alternative_payments_turned_on: int, examples: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    public function fixImportedPupils(bool $dryRun = false, ?User $changedBy = null, ?int $limit = null): array
    {
        return ($this->fixImportedPupils)($dryRun, $changedBy, $limit);
    }

    /**
     * Send the held welcome email to one imported user.
     */
    public function sendWelcomeEmail(User $user): bool
    {
        return ($this->sendImportedWelcomeEmail)($user);
    }

    /**
     * Dry run of importLessonFile() (import:lessons --dry-run): what it would do, writing nothing.
     *
     * `new_lessons_completed` / `new_lessons_booked` are the lessons it would
     * add (dated before today / today onwards). `lessons_already_in_diary` are
     * new refs skipped because the pupil already has a lesson at that date +
     * start time. `lessons_to_complete` are imported lessons dated before
     * today still pending, which it would close off.
     *
     * @return array{counts: array<string, int>, missing_instructor_refs: array<string, int>, missing_student_refs: array<string, int>}
     */
    public function checkLessonFile(string $path): array
    {
        $sorted = $this->sortLessonFileRows($path);
        $counts = $sorted['counts'] + [
            'new_lessons_completed' => 0,
            'new_lessons_booked' => 0,
            'lessons_already_in_diary' => 0,
            'pupils' => 0,
            'instructors' => 0,
            'lessons_to_complete' => 0,
        ];

        /** @var array<string, true> $seen */
        $seen = [];
        /** @var array<int, true> $pupils */
        $pupils = [];
        /** @var array<string, true> $instructors */
        $instructors = [];

        foreach ($sorted['groups'] as $instructorRef => $rows) {
            foreach ($rows as $row) {
                $studentId = $sorted['student_ids'][$row['student_ref']];
                $key = $studentId.'|'.ImportValues::date((string) $row['date'])->toDateString().'|'.ImportValues::time((string) $row['start_time']);

                if (isset($seen[$key]) || $this->importDiaryItem->studentHasLessonAt($studentId, $row)) {
                    $counts['lessons_already_in_diary']++;

                    continue;
                }

                $seen[$key] = true;
                $pupils[$studentId] = true;
                $instructors[$instructorRef] = true;
                $counts[$row['status'] === 'completed' ? 'new_lessons_completed' : 'new_lessons_booked']++;
            }
        }

        $counts['pupils'] = count($pupils);
        $counts['instructors'] = count($instructors);
        $counts['lessons_to_complete'] = $this->completeImportedLesson->pastPendingLessons()->count();

        return [
            'counts' => $counts,
            'missing_instructor_refs' => $sorted['missing_instructor_refs'],
            'missing_student_refs' => $sorted['missing_student_refs'],
        ];
    }

    /**
     * Bring in the lessons from a diary-shaped CSV (e.g. lesson_progress.csv)
     * that the CRM doesn't have yet, then close off past imported lessons.
     *
     * Rules (the master import blueprint follows the same ones):
     * - Only new diary refs. A lesson already imported is never changed by its row.
     * - Only rows whose instructor was imported and whose pupil was imported
     *   and is linked to that instructor (`students.instructor_id`). Anything
     *   else is skipped.
     * - Rows with an unusable date or time (blank, unparseable, before 2000,
     *   end not after start) are skipped, as is a row where the pupil already
     *   has a lesson at that date + start time.
     * - Dated before today → completed (whatever the row's status); today
     *   onwards → booked.
     * - Same path as the bundle's diary (importDiary): one transaction per
     *   instructor, lessons on the pupil's imported order, lesson numbers
     *   recalculated, order synced, caches cleared.
     * - Then every imported lesson dated before today still pending is
     *   completed quietly (closeOffPastImportedLessons).
     *
     * Recorded as an import run (`source = command`); every lesson it creates
     * is mapped (`diary` + `lesson`) like the bundle's. Safe to re-run.
     *
     * @param  (callable(string): void)|null  $progress  Receives one line per instructor
     * @return array{run: ImportRun, totals: array<string, int>}
     *
     * @throws Throwable When an instructor fails (earlier instructors stay imported; the run is marked failed)
     */
    public function importLessonFile(string $path, ?callable $progress = null): array
    {
        $sorted = $this->sortLessonFileRows($path);
        $totals = $sorted['counts'] + ['lessons' => 0, 'lessons_already_in_diary' => 0, 'lessons_marked_completed' => 0];

        $run = ImportRun::create([
            'source' => ImportRun::SOURCE_COMMAND,
            'file_name' => basename($path),
            'status' => ImportRun::STATUS_RUNNING,
            'totals' => $totals + ['instructors_done' => 0, 'instructors_to_import' => count($sorted['groups'])],
        ]);

        $instructorsDone = 0;

        try {
            foreach ($sorted['groups'] as $instructorRef => $rows) {
                $instructor = Instructor::query()->findOrFail($sorted['instructor_ids'][$instructorRef]);

                [$lessons, , , , $duplicates] = DB::transaction(function () use ($instructor, $rows, $run): array {
                    $result = $this->importDiary($instructor, $rows, $run);
                    $this->invalidateImportedInstructorCaches($instructor, $result[2]);

                    return $result;
                });

                $totals['lessons'] += $lessons;
                $totals['lessons_already_in_diary'] += $duplicates;
                $instructorsDone++;

                $run->update([
                    'totals' => $totals + ['instructors_done' => $instructorsDone, 'instructors_to_import' => count($sorted['groups'])],
                ]);

                if ($progress) {
                    $progress(sprintf(
                        '[%s] (%d of %d) %d lesson(s) added, %d already in diary.',
                        $instructorRef,
                        $instructorsDone,
                        count($sorted['groups']),
                        $lessons,
                        $duplicates,
                    ));
                }
            }

            $totals['lessons_marked_completed'] = $this->closeOffPastImportedLessons();

            if ($progress) {
                $progress("{$totals['lessons_marked_completed']} past imported lesson(s) marked completed.");
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'totals' => $totals,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $run->update([
            'status' => ImportRun::STATUS_COMPLETED,
            'totals' => $totals,
            'completed_at' => now(),
        ]);

        return ['run' => $run, 'totals' => $totals];
    }

    /**
     * Turn imported lessons that were really driving tests into practical
     * tests (import:practical-tests). A diary row is a test when its notes
     * start "Driving test" (ConvertImportedLessonToPracticalTestAction::NOTES_PATTERN);
     * it is matched to its imported lesson by diary_ref only.
     *
     * Per pupil, in one transaction: each eligible lesson's calendar item
     * becomes a `practical_test` for the pupil (same times, notes kept and
     * prefixed with the pupil's name) and the lesson is deleted; then lesson
     * numbers are recalculated, the imported orders synced, and the pupil's
     * "Book practical test" checklist item ticked and dated with their latest
     * test date (never moved back to an earlier one). Skipped: rows whose
     * lesson wasn't imported (or is already a test), cancelled lessons, lessons
     * with a payment / payout / reflective log, and a test on a date the pupil
     * already has a practical test. Safe to re-run.
     *
     * @param  (callable(string): void)|null  $progress  Receives a line every 250 pupils
     * @return array<string, int>
     */
    public function importPracticalTests(string $path, bool $dryRun = false, ?callable $progress = null): array
    {
        $counts = [
            'rows' => 0,
            'test_rows' => 0,
            'tests_past' => 0,
            'tests_future' => 0,
            'pupils' => 0,
            'checklists_updated' => 0,
            'already_converted' => 0,
            'skipped_not_imported' => 0,
            'skipped_cancelled' => 0,
            'skipped_banked' => 0,
            'skipped_no_calendar_item' => 0,
            'skipped_has_payment_or_log' => 0,
            'skipped_pupil_has_test_that_day' => 0,
        ];

        /** @var array<int, true> $lessonIds */
        $lessonIds = [];

        foreach (($this->readDiaryCsvRows)($path, ['notes']) as $row) {
            $counts['rows']++;

            if (! preg_match(ConvertImportedLessonToPracticalTestAction::NOTES_PATTERN, (string) $row['notes'])) {
                continue;
            }

            $counts['test_rows']++;
            $ref = (string) $row['diary_ref'];
            $lessonId = ImportMapping::modelIdFor(ImportMapping::ENTITY_LESSON, $ref);

            if ($lessonId !== null) {
                $lessonIds[$lessonId] = true;

                continue;
            }

            $calendarItemId = ImportMapping::modelIdFor(ImportMapping::ENTITY_DIARY, $ref);
            $isConverted = $calendarItemId !== null && CalendarItem::query()
                ->whereKey($calendarItemId)
                ->where('item_type', CalendarItemType::PracticalTest)
                ->exists();

            $counts[$isConverted ? 'already_converted' : 'skipped_not_imported']++;
        }

        $lessonsByStudent = [];

        foreach (array_chunk(array_keys($lessonIds), 1000) as $chunk) {
            foreach (Lesson::query()->with(['order.student', 'calendarItem'])->whereKey($chunk)->get() as $lesson) {
                $lessonsByStudent[(int) $lesson->order?->student_id][] = $lesson;
            }
        }

        $pupilsDone = 0;

        foreach ($lessonsByStudent as $studentId => $lessons) {
            $pupilCounts = $dryRun
                ? $this->convertPupilPracticalTests($studentId, $lessons, true)
                : DB::transaction(fn () => $this->convertPupilPracticalTests($studentId, $lessons, false));

            foreach ($pupilCounts as $key => $count) {
                $counts[$key] += $count;
            }

            $pupilsDone++;

            if ($progress && $pupilsDone % 250 === 0) {
                $progress("{$pupilsDone} of ".count($lessonsByStudent).' pupils done.');
            }
        }

        return $counts;
    }

    /**
     * Attach receipt files from a folder to finance rows already imported
     * (import:receipts). Each file is named after its finance ref
     * (`F-169242.png`) and matched through its `finance` import mapping only.
     *
     * A file is attached only when its finance row has no receipt yet — an
     * existing receipt is never replaced. It goes through the same action as
     * an instructor's own upload (UploadFinanceReceiptAction): same S3 path,
     * photos shrunk and thumbnailed by the queued job, PDFs ready at once.
     * Skipped: types other than pdf/jpg/jpeg/png, refs never imported, and a
     * ref with more than one file. A failed upload is reported and the rest
     * carry on. Safe to re-run.
     *
     * @return array{counts: array<string, int>, rows: array<int, array{file: string, ref: string, finance_id: int|null, instructor: string, date: string, type: string, category: string, amount: string, description: string, outcome: string}>}
     */
    public function importReceipts(string $folder, bool $dryRun = false): array
    {
        if (! is_dir($folder)) {
            throw new RuntimeException("{$folder} is not a folder.");
        }

        $files = collect(File::files($folder))
            ->reject(fn ($file) => str_starts_with($file->getFilename(), '.'))
            ->sortBy(fn ($file) => $file->getFilename(), SORT_NATURAL)
            ->values();

        $filesPerRef = $files->countBy(fn ($file) => strtoupper($file->getFilenameWithoutExtension()));

        $financeIds = ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_FINANCE)
            ->whereIn('source_ref', $filesPerRef->keys()->all())
            ->pluck('model_id', 'source_ref')
            ->mapWithKeys(fn (int $id, string $ref) => [strtoupper($ref) => $id]);

        $finances = InstructorFinance::query()
            ->with('instructor.user')
            ->whereKey($financeIds->values()->all())
            ->get()
            ->keyBy('id');

        $outcomes = [
            'attach' => $dryRun ? 'Would attach' : 'Attached',
            'already_has_receipt' => 'Skipped: already has a receipt',
            'ref_not_imported' => 'Skipped: ref not imported',
            'finance_row_missing' => 'Skipped: finance row deleted',
            'duplicate_file' => 'Skipped: more than one file for this ref',
            'unsupported_type' => 'Skipped: not a pdf, jpg or png',
            'failed' => 'Failed',
        ];
        $counts = array_fill_keys(array_keys($outcomes), 0);
        $rows = [];

        foreach ($files as $file) {
            $ref = strtoupper($file->getFilenameWithoutExtension());
            $financeId = $financeIds->get($ref);
            $finance = $financeId !== null ? $finances->get($financeId) : null;

            $key = match (true) {
                ! in_array(strtolower($file->getExtension()), ['pdf', 'jpg', 'jpeg', 'png'], true) => 'unsupported_type',
                $filesPerRef->get($ref) > 1 => 'duplicate_file',
                $financeId === null => 'ref_not_imported',
                $finance === null => 'finance_row_missing',
                $finance->receipt_path !== null => 'already_has_receipt',
                default => 'attach',
            };

            $outcome = $outcomes[$key];

            if ($key === 'attach' && ! $dryRun) {
                try {
                    ($this->uploadFinanceReceipt)($finance, new UploadedFile($file->getPathname(), $file->getFilename(), null, null, true));
                } catch (Throwable $exception) {
                    $key = 'failed';
                    $outcome = 'Failed: '.$exception->getMessage();
                }
            }

            $counts[$key]++;
            $rows[] = [
                'file' => $file->getFilename(),
                'ref' => $ref,
                'finance_id' => $finance?->id,
                'instructor' => $finance ? "#{$finance->instructor_id} ".($finance->instructor?->name ?? '') : '',
                'date' => $finance?->date?->toDateString() ?? '',
                'type' => (string) ($finance?->type ?? ''),
                'category' => (string) ($finance?->category ?? ''),
                'amount' => $finance?->formatted_amount ?? '',
                'description' => (string) ($finance?->description ?? ''),
                'outcome' => $outcome,
            ];
        }

        return ['counts' => $counts, 'rows' => $rows];
    }

    /**
     * Give imported pupils their pickup points from a locations CSV
     * (import:pickup-points; legacy student_locations.csv: `student_ref`,
     * `locations` joined by `|`).
     *
     * - Pupils are matched by student ref through their `student` mapping only;
     *   a ref never imported is skipped.
     * - A pupil who already has any pickup point in Drive is skipped entirely,
     *   as is a pupil two refs in the file point to (linked by email at import).
     * - Only locations that parse cleanly are added (ParseImportedLocationsAction:
     *   full postcode at the end); anything else is skipped and listed, for
     *   adding by hand.
     * - The first clean location is the default; an unlabelled first one is
     *   "Home", any other unlabelled one "Pick-up point".
     * - No geocoding: latitude/longitude stay empty for a later step.
     *
     * Safe to re-run (a pupil with points is skipped).
     *
     * @return array{counts: array<string, int>, rows: array<int, array{student_ref: string, student_id: int|null, pupil: string, label: string, address: string, postcode: string, default: string, outcome: string}>}
     */
    public function importPickupPoints(string $path, bool $dryRun = false): array
    {
        $studentIds = ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_STUDENT)
            ->pluck('model_id', 'source_ref')
            ->all();

        $pupilsWithPoints = array_flip(StudentPickupPoint::query()->distinct()->pluck('student_id')->all());

        $counts = [
            'pupils_given_points' => 0,
            'points_added' => 0,
            'defaults_set' => 0,
            'locations_skipped' => 0,
            'locations_repeated' => 0,
            'pupils_not_imported' => 0,
            'pupils_already_have_points' => 0,
            'pupils_with_two_refs' => 0,
            'pupils_no_locations' => 0,
            'pupils_no_clean_locations' => 0,
        ];
        $rows = [];
        /** @var array<int, array{ref: string, locations: string}> $pending */
        $pending = [];

        foreach (($this->readCsvColumns)($path, ['student_ref', 'locations']) as $row) {
            $ref = (string) $row['student_ref'];
            $studentId = $studentIds[$ref] ?? null;
            $locations = (string) $row['locations'];

            if ($locations === '') {
                $counts['pupils_no_locations']++;

                continue;
            }

            if ($studentId === null) {
                $counts['pupils_not_imported']++;
                $rows[] = $this->pickupRow($ref, null, '', null, 'Skipped: pupil not imported', $locations);

                continue;
            }

            if (isset($pupilsWithPoints[$studentId])) {
                $counts['pupils_already_have_points']++;
                $rows[] = $this->pickupRow($ref, $studentId, '', null, 'Skipped: pupil already has a pick-up point', $locations);

                continue;
            }

            if (isset($pending[$studentId])) {
                $pending[$studentId]['other_refs'][] = ['ref' => $ref, 'locations' => $locations];

                continue;
            }

            $pending[$studentId] = ['ref' => $ref, 'locations' => $locations, 'other_refs' => []];
        }

        foreach ($pending as $studentId => $entry) {
            if ($entry['other_refs'] === []) {
                continue;
            }

            foreach ([['ref' => $entry['ref'], 'locations' => $entry['locations']], ...$entry['other_refs']] as $duplicate) {
                $counts['pupils_with_two_refs']++;
                $rows[] = $this->pickupRow($duplicate['ref'], $studentId, '', null, 'Skipped: another ref in the file is the same pupil', $duplicate['locations']);
            }

            unset($pending[$studentId]);
        }

        foreach (array_chunk(array_keys($pending), 1000, true) as $chunk) {
            $names = Student::query()->whereKey($chunk)->get(['id', 'first_name', 'surname'])
                ->mapWithKeys(fn (Student $student) => [$student->id => trim($student->first_name.' '.$student->surname)]);

            foreach ($chunk as $studentId) {
                $ref = $pending[$studentId]['ref'];
                $pupil = (string) ($names[$studentId] ?? '');
                $parsed = ($this->parseImportedLocations)($pending[$studentId]['locations']);
                $clean = array_values(array_filter($parsed, fn (array $location) => $location['skip_reason'] === null));

                foreach ($parsed as $location) {
                    if ($location['skip_reason'] !== null) {
                        $counts[$location['postcode'] === null && $location['skip_reason'] === ParseImportedLocationsAction::REPEAT_REASON ? 'locations_repeated' : 'locations_skipped']++;
                        $rows[] = $this->pickupRow($ref, $studentId, $pupil, null, 'Skipped: '.$location['skip_reason'], $location['raw']);
                    }
                }

                if ($clean === []) {
                    $counts['pupils_no_clean_locations']++;

                    continue;
                }

                $points = CreateImportedPickupPointsAction::plan($clean);
                $saved = $dryRun || ($this->createImportedPickupPoints)($studentId, $points);

                if (! $saved) {
                    $counts['pupils_already_have_points']++;
                    $rows[] = $this->pickupRow($ref, $studentId, $pupil, null, 'Skipped: pupil already has a pick-up point', $pending[$studentId]['locations']);

                    continue;
                }

                $counts['pupils_given_points']++;
                $counts['points_added'] += count($points);
                $counts['defaults_set']++;

                foreach ($points as $point) {
                    $rows[] = $this->pickupRow($ref, $studentId, $pupil, $point, $dryRun ? 'Would add' : 'Added');
                }
            }
        }

        return ['counts' => $counts, 'rows' => $rows];
    }

    /**
     * @param  array{label: string, address: string, postcode: string, is_default: bool}|null  $point
     * @return array{student_ref: string, student_id: int|null, pupil: string, label: string, address: string, postcode: string, default: string, outcome: string}
     */
    protected function pickupRow(string $ref, ?int $studentId, string $pupil, ?array $point, string $outcome, string $raw = ''): array
    {
        return [
            'student_ref' => $ref,
            'student_id' => $studentId,
            'pupil' => $pupil,
            'label' => $point['label'] ?? '',
            'address' => $point['address'] ?? $raw,
            'postcode' => $point['postcode'] ?? '',
            'default' => $point ? ($point['is_default'] ? 'Yes' : 'No') : '',
            'outcome' => $outcome,
        ];
    }

    /**
     * Fill pupils' date of birth and theory / practical passes from the
     * legacy student details export (import:student-details;
     * Students_data.csv: `ID`, `Date of Birth`, `Theory Test Passed`,
     * `Practical Test Passed`). Only the file's own data is used.
     *
     * - `ID` 119827 is the pupil imported as `STU-119827` (matched through the
     *   `student` mapping only); IDs never imported, and deleted pupils, are skipped.
     * - Date of birth (dd/mm/yyyy): set only when the pupil has none; a date
     *   that doesn't parse or is not in the past is skipped.
     * - Theory "Yes" → tick "Sit theory test"; practical "Yes" → tick "Sit
     *   practical test". No dates, nothing unticked, "No"/blank change nothing.
     * - Nothing else changes (no lessons, status, "Book …" items, licence, email).
     *
     * One transaction per pupil. Safe to re-run.
     *
     * @return array{counts: array<string, int>, rows: array<int, array{id: string, student_id: int|null, pupil: string, field: string, value: string, outcome: string}>}
     */
    public function importStudentDetails(string $path, bool $dryRun = false): array
    {
        $columns = ['id', 'date of birth', 'theory test passed', 'practical test passed'];
        $checklistFields = [
            'theory test passed' => ['key' => 'sit_theory_test', 'field' => 'Sit theory test', 'count' => 'theory'],
            'practical test passed' => ['key' => 'sit_practical_test', 'field' => 'Sit practical test', 'count' => 'practical'],
        ];

        $studentIds = ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_STUDENT)
            ->pluck('model_id', 'source_ref')
            ->all();

        $counts = [
            'rows' => 0,
            'pupils_matched' => 0,
            'pupils_not_imported' => 0,
            'pupils_deleted' => 0,
            'dob_set' => 0,
            'dob_already_set' => 0,
            'dob_invalid' => 0,
            'theory_ticked' => 0,
            'theory_already_ticked' => 0,
            'practical_ticked' => 0,
            'practical_already_ticked' => 0,
        ];
        $rows = [];
        /** @var array<int, array<string, string|int>> $matched */
        $matched = [];

        foreach (($this->readCsvColumns)($path, $columns) as $row) {
            $counts['rows']++;
            $studentId = $studentIds['STU-'.$row['id']] ?? null;

            if ($studentId === null) {
                $counts['pupils_not_imported']++;

                continue;
            }

            $counts['pupils_matched']++;
            $matched[$studentId] = $row;
        }

        $today = now()->startOfDay();
        $verb = $dryRun ? 'Would set' : 'Set';
        $tickVerb = $dryRun ? 'Would tick' : 'Ticked';

        foreach (array_chunk(array_keys($matched), 500, true) as $chunk) {
            $students = Student::query()->whereKey($chunk)->get()->keyBy('id');

            foreach ($chunk as $studentId) {
                $student = $students->get($studentId);

                if ($student === null) {
                    $counts['pupils_deleted']++;

                    continue;
                }

                $row = $matched[$studentId];
                $pupil = trim($student->first_name.' '.$student->surname);
                $id = (string) $row['id'];
                $pupilRows = [];

                $apply = function () use ($student, $row, $dryRun, $today, $checklistFields, $id, $studentId, $pupil, $verb, $tickVerb, &$counts, &$pupilRows): void {
                    $dobValue = (string) $row['date of birth'];

                    if ($dobValue !== '') {
                        $dob = ImportValues::date($dobValue);

                        if ($dob === null || ! $dob->lessThan($today)) {
                            $counts['dob_invalid']++;
                            $pupilRows[] = ['id' => $id, 'student_id' => $studentId, 'pupil' => $pupil, 'field' => 'Date of birth', 'value' => $dobValue, 'outcome' => 'Skipped: not a valid past date'];
                        } elseif ($student->date_of_birth !== null) {
                            $counts['dob_already_set']++;
                        } else {
                            if (! $dryRun) {
                                $student->update(['date_of_birth' => $dob->toDateString()]);
                            }

                            $counts['dob_set']++;
                            $pupilRows[] = ['id' => $id, 'student_id' => $studentId, 'pupil' => $pupil, 'field' => 'Date of birth', 'value' => $dob->toDateString(), 'outcome' => $verb];
                        }
                    }

                    foreach ($checklistFields as $column => $target) {
                        if (strcasecmp((string) $row[$column], 'yes') !== 0) {
                            continue;
                        }

                        if (($this->tickImportedChecklistItem)($student, $target['key'], $dryRun)) {
                            $counts[$target['count'].'_ticked']++;
                            $pupilRows[] = ['id' => $id, 'student_id' => $studentId, 'pupil' => $pupil, 'field' => $target['field'], 'value' => 'Yes', 'outcome' => $tickVerb];
                        } else {
                            $counts[$target['count'].'_already_ticked']++;
                        }
                    }
                };

                $dryRun ? $apply() : DB::transaction($apply);

                array_push($rows, ...$pupilRows);
            }
        }

        return ['counts' => $counts, 'rows' => $rows];
    }

    /**
     * Import instructors' mileage trips (import:mileage; legacy mileage.csv).
     *
     * - Instructors matched by ref (`instructor` mapping or DRIVE-<id>) only.
     * - Each trip's `mileage_ref` is mapped, so a re-run skips it.
     * - Only clean rows (ImportMileageLogAction::parse); others are skipped and listed.
     * - A trip the instructor already has (same date + odometers) is skipped.
     * - **No vehicles are created** and trips get no vehicle: the instructor's
     *   primary-vehicle setup (BackfillPrimaryVehicleAction) tags every trip
     *   with no vehicle later. The registration is kept in the trip's notes.
     *
     * One transaction per instructor; recorded as an import run (`source = command`).
     *
     * @return array{counts: array<string, int>, rows: array<int, array{mileage_ref: string, instructor_ref: string, instructor_id: int|null, date: string, start: string, end: string, miles: string, type: string, notes: string, outcome: string}>}
     */
    public function importMileage(string $path, bool $dryRun = false): array
    {
        $columns = ['mileage_ref', 'instructor_ref', 'date', 'end_date', 'start_odometer', 'end_odometer', 'type', 'vehicle_reg'];

        $importedRefs = array_flip(
            ImportMapping::query()->where('entity', ImportMapping::ENTITY_MILEAGE)->pluck('source_ref')->all()
        );

        $counts = [
            'rows' => 0,
            'trips_added' => 0,
            'business' => 0,
            'personal' => 0,
            'with_registration_note' => 0,
            'instructors' => 0,
            'already_imported' => 0,
            'skipped_not_clean' => 0,
            'skipped_no_instructor' => 0,
            'skipped_already_logged' => 0,
        ];
        $rows = [];
        /** @var array<int, array<int, array{row: array<string, string|int>, data: array<string, mixed>}>> $byInstructor */
        $byInstructor = [];
        /** @var array<string, int|null> $instructorIds */
        $instructorIds = [];

        $describe = fn (array $row, ?int $instructorId, ?array $data, string $outcome): array => [
            'mileage_ref' => (string) $row['mileage_ref'],
            'instructor_ref' => (string) $row['instructor_ref'],
            'instructor_id' => $instructorId,
            'date' => (string) ($data['date'] ?? $row['date']),
            'start' => (string) ($data['start_mileage'] ?? $row['start_odometer']),
            'end' => (string) ($data['end_mileage'] ?? $row['end_odometer']),
            'miles' => isset($data['miles']) ? (string) $data['miles'] : '',
            'type' => (string) ($data['type'] ?? $row['type']),
            'notes' => (string) ($data['notes'] ?? ''),
            'outcome' => $outcome,
        ];

        foreach (($this->readCsvColumns)($path, $columns) as $row) {
            $counts['rows']++;

            if (isset($importedRefs[(string) $row['mileage_ref']])) {
                $counts['already_imported']++;

                continue;
            }

            $instructorRef = (string) $row['instructor_ref'];
            $instructorIds[$instructorRef] ??= $instructorRef !== '' ? $this->resolveInstructorId($instructorRef) : null;
            $instructorId = $instructorIds[$instructorRef];

            if ($instructorId === null) {
                $counts['skipped_no_instructor']++;
                $rows[] = $describe($row, null, null, 'Skipped: instructor not imported');

                continue;
            }

            $parsed = ImportMileageLogAction::parse($row);

            if ($parsed['data'] === null) {
                $counts['skipped_not_clean']++;
                $rows[] = $describe($row, $instructorId, null, 'Skipped: '.$parsed['skip_reason']);

                continue;
            }

            $byInstructor[$instructorId][] = ['row' => $row, 'data' => $parsed['data']];
        }

        $run = $dryRun ? null : ImportRun::create([
            'source' => ImportRun::SOURCE_COMMAND,
            'file_name' => basename($path),
            'status' => ImportRun::STATUS_RUNNING,
        ]);

        try {
            foreach ($byInstructor as $instructorId => $trips) {
                $process = function () use ($instructorId, $trips, $run, $dryRun, $describe, &$counts, &$rows): void {
                    /** @var array<string, true> $seen */
                    $seen = [];
                    $added = 0;

                    foreach ($trips as ['row' => $row, 'data' => $data]) {
                        $key = $data['date'].'|'.$data['start_mileage'].'|'.$data['end_mileage'];

                        if (isset($seen[$key]) || $this->importMileageLog->instructorHasTrip($instructorId, $data)) {
                            $counts['skipped_already_logged']++;
                            $rows[] = $describe($row, $instructorId, $data, 'Skipped: instructor already has this trip');

                            continue;
                        }

                        $seen[$key] = true;

                        if (! $dryRun) {
                            ($this->importMileageLog)($instructorId, (string) $row['mileage_ref'], $data, $run);
                        }

                        $added++;
                        $counts['trips_added']++;
                        $counts[$data['type']]++;
                        $counts['with_registration_note'] += (int) str_contains((string) $data['notes'], 'Vehicle: ');
                        $rows[] = $describe($row, $instructorId, $data, $dryRun ? 'Would add' : 'Added');
                    }

                    $counts['instructors'] += (int) ($added > 0);
                };

                $dryRun ? $process() : DB::transaction($process);
            }
        } catch (Throwable $exception) {
            $run?->update(['status' => ImportRun::STATUS_FAILED, 'totals' => $counts, 'error' => $exception->getMessage(), 'completed_at' => now()]);

            throw $exception;
        }

        $run?->update(['status' => ImportRun::STATUS_COMPLETED, 'totals' => $counts, 'completed_at' => now()]);

        return ['counts' => $counts, 'rows' => $rows];
    }

    /**
     * Convert one pupil's imported test lessons (see importPracticalTests).
     *
     * @param  array<int, Lesson>  $lessons
     * @return array<string, int>
     */
    protected function convertPupilPracticalTests(int $studentId, array $lessons, bool $dryRun): array
    {
        $counts = ['tests_past' => 0, 'tests_future' => 0, 'pupils' => 0, 'checklists_updated' => 0];
        $today = now()->toDateString();
        /** @var array<string, true> $testDates */
        $testDates = [];
        /** @var array<int, Order> $orders */
        $orders = [];
        /** @var array<int, array<string, true>> $datesByInstructor */
        $datesByInstructor = [];

        usort($lessons, fn (Lesson $a, Lesson $b) => $a->date?->toDateString() <=> $b->date?->toDateString());

        foreach ($lessons as $lesson) {
            $blocker = $this->convertImportedLessonToPracticalTest->blocker($lesson);

            if ($blocker !== null) {
                $counts['skipped_'.$blocker] = ($counts['skipped_'.$blocker] ?? 0) + 1;

                continue;
            }

            $date = $lesson->date->toDateString();

            if (isset($testDates[$date]) || $this->convertImportedLessonToPracticalTest->pupilHasTestOn($studentId, $date, $lesson->calendar_item_id)) {
                $counts['skipped_pupil_has_test_that_day'] = ($counts['skipped_pupil_has_test_that_day'] ?? 0) + 1;

                continue;
            }

            if (! $dryRun) {
                ($this->convertImportedLessonToPracticalTest)($lesson);
            }

            $testDates[$date] = true;
            $orders[$lesson->order_id] = $lesson->order;
            $datesByInstructor[$lesson->instructor_id][$date] = true;
            $counts[$date < $today ? 'tests_past' : 'tests_future']++;
        }

        if ($testDates === []) {
            return $counts;
        }

        $counts['pupils'] = 1;
        $student = reset($orders)->student;
        $counts['checklists_updated'] = (int) ($this->syncLatestPracticalTestDate)($student, max(array_keys($testDates)), $dryRun);

        if ($dryRun) {
            return $counts;
        }

        foreach ($orders as $order) {
            ($this->syncImportedOrder)($order);
        }

        ($this->recalculateStudentLessonNumbers)($studentId);

        foreach (Instructor::query()->whereKey(array_keys($datesByInstructor))->get() as $instructor) {
            $this->invalidateImportedInstructorCaches($instructor, $datesByInstructor[$instructor->id]);
        }

        return $counts;
    }

    /**
     * Quietly complete every lesson the importer created that is dated before
     * today and still pending (CompleteImportedLessonAction — imported orders
     * only, no sign-off pipeline: no emails, payouts or lesson payments), then
     * sync the touched orders and clear the instructors' calendar caches.
     *
     * @return int Lessons completed
     */
    protected function closeOffPastImportedLessons(): int
    {
        return DB::transaction(function (): int {
            $completed = 0;
            /** @var array<int, true> $orderIds */
            $orderIds = [];
            /** @var array<int, array<string, true>> $datesByInstructor */
            $datesByInstructor = [];

            foreach ($this->completeImportedLesson->pastPendingLessons() as $lesson) {
                if (! $this->completeImportedLesson->complete($lesson)) {
                    continue;
                }

                $completed++;
                $orderIds[$lesson->order_id] = true;
                $datesByInstructor[$lesson->instructor_id][$lesson->date->toDateString()] = true;
            }

            foreach (Order::query()->whereKey(array_keys($orderIds))->get() as $order) {
                ($this->syncImportedOrder)($order);
            }

            foreach (Instructor::query()->whereKey(array_keys($datesByInstructor))->get() as $instructor) {
                $this->invalidateImportedInstructorCaches($instructor, $datesByInstructor[$instructor->id]);
            }

            return $completed;
        });
    }

    /**
     * Read a diary-shaped CSV and keep the rows importLessonFile() adds,
     * grouped by instructor ref, with `status` set by date (completed before
     * today, booked from today). Everything else is counted as skipped.
     *
     * @return array{groups: array<string, array<int, array<string, string|int>>>, instructor_ids: array<string, int>, student_ids: array<string, int>, counts: array<string, int>, missing_instructor_refs: array<string, int>, missing_student_refs: array<string, int>}
     */
    protected function sortLessonFileRows(string $path): array
    {
        $importedDiaryRefs = array_flip(
            ImportMapping::query()->where('entity', ImportMapping::ENTITY_DIARY)->pluck('source_ref')->all()
        );

        $studentIds = ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_STUDENT)
            ->pluck('model_id', 'source_ref')
            ->all();

        $studentInstructorIds = Student::query()->pluck('instructor_id', 'id')->all();

        $counts = [
            'rows' => 0,
            'already_imported' => 0,
            'skipped_cancelled' => 0,
            'skipped_bad_date' => 0,
            'skipped_no_instructor' => 0,
            'skipped_no_pupil' => 0,
            'skipped_pupil_not_linked' => 0,
        ];
        $groups = [];
        /** @var array<string, int|null> $instructorIds */
        $instructorIds = [];
        $missingInstructorRefs = [];
        $missingStudentRefs = [];
        $earliestDate = ImportValues::date('2000-01-01');
        $today = now()->startOfDay();

        foreach (($this->readDiaryCsvRows)($path) as $row) {
            $counts['rows']++;

            if (isset($importedDiaryRefs[(string) $row['diary_ref']])) {
                $counts['already_imported']++;

                continue;
            }

            $date = ImportValues::date((string) $row['date']);
            $startTime = ImportValues::time((string) $row['start_time']);
            $endTime = ImportValues::time((string) $row['end_time']);

            if ($date === null || $date->lessThan($earliestDate) || $startTime === null || $endTime === null || $endTime <= $startTime) {
                $counts['skipped_bad_date']++;

                continue;
            }

            if (ImportDiaryItemAction::resolveStatus($row) === 'cancelled') {
                $counts['skipped_cancelled']++;

                continue;
            }

            $instructorRef = (string) $row['instructor_ref'];
            $instructorIds[$instructorRef] ??= $instructorRef !== '' ? $this->resolveInstructorId($instructorRef) : null;

            if ($instructorIds[$instructorRef] === null) {
                $counts['skipped_no_instructor']++;
                $missingInstructorRefs[$instructorRef] = ($missingInstructorRefs[$instructorRef] ?? 0) + 1;

                continue;
            }

            $studentRef = (string) $row['student_ref'];
            $studentId = $studentIds[$studentRef] ?? null;

            if ($studentId === null) {
                $counts['skipped_no_pupil']++;
                $missingStudentRefs[$studentRef] = ($missingStudentRefs[$studentRef] ?? 0) + 1;

                continue;
            }

            $linkedInstructorId = $studentInstructorIds[$studentId] ?? null;

            if ($linkedInstructorId === null || (int) $linkedInstructorId !== $instructorIds[$instructorRef]) {
                $counts['skipped_pupil_not_linked']++;

                continue;
            }

            $row['status'] = $date->lessThan($today) ? 'completed' : 'booked';
            $groups[$instructorRef][] = $row;
        }

        arsort($missingInstructorRefs);
        arsort($missingStudentRefs);

        return [
            'groups' => $groups,
            'instructor_ids' => array_filter($instructorIds, fn (?int $id) => $id !== null),
            'student_ids' => $studentIds,
            'counts' => $counts,
            'missing_instructor_refs' => $missingInstructorRefs,
            'missing_student_refs' => $missingStudentRefs,
        ];
    }

    /**
     * Instructor ids for the diary's instructor refs that already exist
     * (imported or linked earlier, or DRIVE-<id>), keyed by ref.
     *
     * @param  array<int, array<string, string|int>>  $rows
     * @return array<string, int>
     */
    protected function existingInstructorIds(array $rows): array
    {
        $ids = [];

        foreach (array_unique(array_map(fn (array $row) => trim((string) ($row['instructor_ref'] ?? '')), $rows)) as $ref) {
            $id = $ref !== '' ? $this->resolveInstructorId($ref) : null;

            if ($id !== null) {
                $ids[$ref] = $id;
            }
        }

        return $ids;
    }

    /**
     * Resolve an instructor from a CRM instructor ID, a DRIVE-<id> ref or an import instructor_ref.
     */
    public function resolveInstructorId(string $idOrRef): ?int
    {
        $mappedId = ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $idOrRef);

        if ($mappedId !== null) {
            return $mappedId;
        }

        $id = ImportValues::driveInstructorId($idOrRef) ?? (ctype_digit($idOrRef) ? (int) $idOrRef : null);

        return $id !== null && Instructor::query()->whereKey($id)->exists() ? $id : null;
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

        $skippedStudentRefs = [];

        foreach ($sections['students'] as $row) {
            $result = ($this->importStudent)($instructor, $row, $run);
            $counts['students'] += (int) $result['created'];
            $counts['students_linked'] += (int) $result['linked'];

            if ($result['student'] === null) {
                $skippedStudentRefs[(string) $row['student_ref']] = true;
                $counts['students_skipped']++;
            }
        }

        $diaryRows = array_filter(
            $sections['diary'],
            fn (array $row) => ! isset($skippedStudentRefs[trim((string) ($row['student_ref'] ?? ''))]),
        );
        $counts['lessons_skipped'] = count($sections['diary']) - count($diaryRows);

        [$lessons, $blocks, $dates, $summariesFilled, $duplicates, $lessonsCompleted] = $this->importDiary($instructor, $diaryRows, $run);
        $counts['lessons'] = $lessons;
        $counts['lesson_summaries_filled'] = $summariesFilled;
        $counts['lessons_already_in_diary'] = $duplicates;
        $counts['lessons_marked_completed'] = $lessonsCompleted;
        $counts['diary_blocks'] = $blocks;

        foreach ($sections['finances'] as $row) {
            $finance = ($this->importFinance)($instructor, $row, $run, $zip, $attachments);

            if ($finance) {
                $counts['finances']++;
                $counts['receipts'] += $finance->receipt_path ? 1 : 0;
            }
        }

        $this->invalidateImportedInstructorCaches($instructor, $dates);

        return $counts;
    }

    /**
     * Once the transaction commits, clear the instructor's calendar cache for
     * every touched date and their grouped pupils list.
     *
     * @param  array<string, true>  $dates
     */
    protected function invalidateImportedInstructorCaches(Instructor $instructor, array $dates): void
    {
        DB::afterCommit(function () use ($instructor, $dates): void {
            foreach (array_keys($dates) as $date) {
                $this->instructorCalendarService->invalidateCalendarCache($instructor->id, $date);
            }

            $this->invalidate($this->cacheKey('instructor', $instructor->id, 'grouped_students'));
        });
    }

    /**
     * Import diary rows chronologically so each student's lesson numbers run
     * in date order, then settle each touched imported order. Rows imported
     * on an earlier run only fill their lesson's summary if it is still empty.
     * A new lesson row is skipped when the student already has a lesson at
     * that date + start time (e.g. a linked Drive+ pupil, or a lesson the old
     * system re-created under a new ref). The imported order is only created
     * once a lesson is actually added. A still-pending imported lesson the
     * row now marks completed is completed quietly (CompleteImportedLessonAction).
     *
     * @param  array<int, array<string, string|int>>  $rows
     * @return array{0: int, 1: int, 2: array<string, true>, 3: int, 4: int, 5: int} lessons, blocks, touched dates, summaries filled, duplicates skipped, lessons completed
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
        $summariesFilled = 0;
        $duplicates = 0;
        $lessonsCompleted = 0;
        /** @var array<int, true> $completedOrderIds */
        $completedOrderIds = [];
        $completionCandidates = $this->completeImportedLesson->candidatesFor($instructor);

        /** @var array<int, Order> $orders */
        $orders = [];
        /** @var array<int, int> $nextLessonNumbers */
        $nextLessonNumbers = [];

        foreach ($rows as $row) {
            $studentRef = trim((string) ($row['student_ref'] ?? ''));
            $order = null;
            $lessonNumber = 0;

            $summariesFilled += (int) ($this->fillImportedLessonSummary)($row);

            $completedLesson = ($this->completeImportedLesson)($row, $completionCandidates);

            if ($completedLesson) {
                $lessonsCompleted++;
                $completedOrderIds[$completedLesson->order_id] = true;
                $dates[$completedLesson->date->toDateString()] = true;
            }

            if ($studentRef !== '') {
                $student = Student::query()->findOrFail(
                    ImportMapping::modelIdFor(ImportMapping::ENTITY_STUDENT, $studentRef)
                );

                $isNewLesson = ImportMapping::modelIdFor(ImportMapping::ENTITY_DIARY, trim((string) $row['diary_ref'])) === null
                    && ImportDiaryItemAction::resolveStatus($row) !== 'cancelled';

                if (! $isNewLesson) {
                    continue;
                }

                if ($this->importDiaryItem->studentHasLessonAt($student->id, $row)) {
                    $duplicates++;

                    continue;
                }

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

        $syncedOrderIds = array_map(fn (Order $order) => $order->id, $orders);

        foreach (array_diff(array_keys($completedOrderIds), $syncedOrderIds) as $orderId) {
            ($this->syncImportedOrder)(Order::query()->findOrFail($orderId));
        }

        return [$lessons, $blocks, $dates, $summariesFilled, $duplicates, $lessonsCompleted];
    }

    /**
     * @param  array<string, string|int>|null  $row
     * @param  array<string, int>  $counts
     */
    protected function resolveInstructor(string $instructorRef, ?array $row, ImportRun $run, array &$counts): Instructor
    {
        $driveInstructorId = ImportValues::driveInstructorId($instructorRef);

        if ($driveInstructorId !== null) {
            $counts['instructors_linked']++;

            return Instructor::query()->findOrFail($driveInstructorId);
        }

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
