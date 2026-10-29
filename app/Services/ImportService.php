<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\BuildImportTemplateAction;
use App\Actions\Import\FixImportedPupilsAction;
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
use App\Jobs\ImportBundleJob;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Support\ImportValues;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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
        'students_skipped',
        'lessons',
        'lessons_skipped',
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
        protected FixImportedPupilsAction $fixImportedPupils,
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
     * @return array{bundle: array<string, array<int|string, mixed>>, row_counts: array<string, int>, errors: array<int, array{file: string, line: int, message: string}>, skipped: array<int, array{file: string, line: int, message: string}>}
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
     * One-off clean-up of imported pupils and instructors (see FixImportedPupilsAction).
     *
     * @return array{pupils: int, alternative_payments_turned_on: int, pupil_phones_fixed: int, instructors: int, instructor_phones_fixed: int, examples: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    public function fixImportedPupils(bool $dryRun = false, ?User $changedBy = null): array
    {
        return ($this->fixImportedPupils)($dryRun, $changedBy);
    }

    /**
     * Send the held welcome email to one imported user.
     */
    public function sendWelcomeEmail(User $user): bool
    {
        return ($this->sendImportedWelcomeEmail)($user);
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

        [$lessons, $blocks, $dates] = $this->importDiary($instructor, $diaryRows, $run);
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
