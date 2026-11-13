<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\CheckStagedImportAction;
use App\Actions\Import\ImportStagedDiaryAction;
use App\Actions\Import\ImportStagedFinancesAction;
use App\Actions\Import\ImportStagedInstructorAction;
use App\Actions\Import\ImportStagedMileageAction;
use App\Actions\Import\ImportStagedProgressAction;
use App\Actions\Import\ImportStagedReflectiveLogsAction;
use App\Actions\Import\ImportStagedStudentsAction;
use App\Actions\Import\ImportStagedTestsAction;
use App\Actions\Import\StageImportBundleAction;
use App\Actions\Import\VerifyImportManifestAction;
use App\Jobs\FinishImportJob;
use App\Jobs\ImportStageJob;
use App\Jobs\PrepareImportJob;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Support\ImportCheckReport;
use App\Support\ImportValues;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The Data Import pipeline for large zips (the Go Roadie export structure,
 * and the original 5-file bundle): the zip is read once into
 * import_staged_rows, checked, then imported in stages by small jobs.
 *
 * Rules for every stage: add only what is missing, fill blanks only, never
 * overwrite or delete, never email.
 */
class StagedImportService extends BaseService
{
    /**
     * The uploaded zip waits here for the queue worker, which may not share
     * the web server's disk. The bucket is public, so the name is random and
     * the zip is deleted as soon as its rows are staged.
     */
    protected const UPLOAD_DISK = 's3';

    protected const UPLOAD_DIRECTORY = 'imports';

    protected const DELETE_BATCH = 10000;

    /** Instructors per job: small enough for each job to finish in a few minutes. */
    protected const INSTRUCTORS_PER_JOB = 10;

    /**
     * The import stages, in order (each one needs the ones before it), and
     * the staged files whose instructor refs each stage works through.
     *
     * @var array<string, array<int, string>>
     */
    public const STAGES = [
        'instructors' => ['instructors', 'coverage'],
        'students' => ['students', 'pickup_points'],
        'diary' => ['diary'],
        'tests' => ['tests'],
        'progress' => ['progress'],
        'reflective_logs' => ['reflective_logs'],
        'finances' => ['finances'],
        'mileage' => ['mileage'],
    ];

    public function __construct(
        protected StageImportBundleAction $stageImportBundle,
        protected VerifyImportManifestAction $verifyImportManifest,
        protected CheckStagedImportAction $checkStagedImport,
        protected ImportStagedInstructorAction $importStagedInstructor,
        protected ImportStagedStudentsAction $importStagedStudents,
        protected ImportStagedDiaryAction $importStagedDiary,
        protected ImportStagedTestsAction $importStagedTests,
        protected ImportStagedProgressAction $importStagedProgress,
        protected ImportStagedReflectiveLogsAction $importStagedReflectiveLogs,
        protected ImportStagedFinancesAction $importStagedFinances,
        protected ImportStagedMileageAction $importStagedMileage,
        protected ImportService $importService,
        protected InstructorCalendarService $instructorCalendarService,
    ) {}

    /**
     * The instructor refs a stage has rows for, in a stable order.
     *
     * @return array<int, string>
     */
    public function instructorRefsFor(ImportRun $run, string $stage): array
    {
        return DB::table('import_staged_rows')
            ->where('import_run_id', $run->id)
            ->whereIn('file', self::STAGES[$stage])
            ->whereNotNull('instructor_ref')
            ->distinct()
            ->orderBy('instructor_ref')
            ->pluck('instructor_ref')
            ->all();
    }

    /**
     * Import one stage for one instructor ref, in one transaction (all of
     * it or none of it, so a retried job starts clean). Add-only: nothing
     * already in Drive is overwritten or deleted; nothing is emailed.
     *
     * @return array<string, int> Counts for the run's totals
     */
    public function importStage(ImportRun $run, string $stage, string $instructorRef): array
    {
        $result = DB::transaction(fn (): array => match ($stage) {
            'instructors' => ['counts' => ($this->importStagedInstructor)($run, $instructorRef)],
            'students' => ['counts' => ($this->importStagedStudents)($run, $instructorRef)],
            'diary' => ($this->importStagedDiary)($run, $instructorRef),
            'tests' => ['counts' => ($this->importStagedTests)($run, $instructorRef)],
            'progress' => ['counts' => ($this->importStagedProgress)($run, $instructorRef)],
            'reflective_logs' => ['counts' => ($this->importStagedReflectiveLogs)($run, $instructorRef)],
            'finances' => ['counts' => ($this->importStagedFinances)($run, $instructorRef)],
            'mileage' => ['counts' => ($this->importStagedMileage)($run, $instructorRef)],
        });

        if (in_array($stage, ['students', 'diary', 'tests'], true)) {
            $this->clearInstructorCaches($instructorRef, array_keys($result['dates'] ?? []));
        }

        return $result['counts'];
    }

    /**
     * Last step: quietly complete every importer-created lesson dated before
     * today that is still pending (imported orders only; no emails, payments
     * or payouts), as agreed for every lesson import.
     *
     * @return int Lessons completed
     */
    public function closeOffPastLessons(): int
    {
        return $this->importService->closeOffPastImportedLessons();
    }

    /**
     * @param  array<int, string>  $dates
     */
    protected function clearInstructorCaches(string $instructorRef, array $dates): void
    {
        $instructorId = ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $instructorRef)
            ?? ImportValues::driveInstructorId($instructorRef);

        if ($instructorId === null) {
            return;
        }

        foreach ($dates as $date) {
            $this->instructorCalendarService->invalidateCalendarCache($instructorId, $date);
        }

        $this->invalidate($this->cacheKey('instructor', $instructorId, 'grouped_students'));
    }

    /**
     * Store an uploaded zip and queue a check of it (writes nothing to
     * Drive's data). The page polls the run for the report.
     */
    public function queueCheck(UploadedFile $file, ?int $userId): ImportRun
    {
        return $this->queueRun($file, $userId, true);
    }

    /**
     * Store an uploaded zip and queue its import: read and check it, then
     * import it stage by stage in the background. Nothing is emailed.
     *
     * @throws RuntimeException When another import or check is still running
     */
    public function queueImport(UploadedFile $file, ?int $userId): ImportRun
    {
        return $this->queueRun($file, $userId, false);
    }

    /**
     * Whether an import or check is queued or running.
     */
    public function hasActiveRun(): bool
    {
        return ImportRun::query()
            ->whereIn('status', [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING])
            ->exists();
    }

    protected function queueRun(UploadedFile $file, ?int $userId, bool $dryRun): ImportRun
    {
        if (! $dryRun && $this->hasActiveRun()) {
            throw new RuntimeException('Another import or check is still running. Wait for it to finish, then try again.');
        }

        $storedZipPath = $file->storeAs(self::UPLOAD_DIRECTORY, Str::random(40).'.zip', self::UPLOAD_DISK);

        if ($storedZipPath === false) {
            throw new RuntimeException('Could not store the uploaded zip.');
        }

        $run = ImportRun::create([
            'user_id' => $userId,
            'source' => ImportRun::SOURCE_UPLOAD,
            'file_name' => $file->getClientOriginalName(),
            'status' => ImportRun::STATUS_QUEUED,
            'dry_run' => $dryRun,
        ]);

        PrepareImportJob::dispatch($run, $storedZipPath);

        return $run;
    }

    /**
     * Read the zip into staging, check it against its manifest and check
     * every row (called by PrepareImportJob). The report is saved on the run
     * (`totals`). A check-only run then clears its staged rows and finishes.
     *
     * The zip is deleted once its rows are staged, whatever the outcome.
     */
    public function prepareImportRun(ImportRun $run, string $storedZipPath): void
    {
        $run->refresh();

        if (! in_array($run->status, [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING], true)) {
            Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);

            return;
        }

        ini_set('memory_limit', '1024M');

        $run->update(['status' => ImportRun::STATUS_RUNNING, 'stage' => 'reading']);
        $localZipPath = tempnam(sys_get_temp_dir(), 'drivecrm-import-');

        try {
            $stream = Storage::disk(self::UPLOAD_DISK)->readStream($storedZipPath);

            if ($stream === null) {
                throw new RuntimeException('The uploaded zip is no longer available. Upload it again.');
            }

            file_put_contents($localZipPath, $stream);

            $staged = ($this->stageImportBundle)($run, $localZipPath);
            $manifest = ($this->verifyImportManifest)($localZipPath, $staged['files']);

            Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);
            $run->update(['stage' => 'checking']);

            $report = new ImportCheckReport;

            foreach ($staged['errors'] as $error) {
                $report->error($error['file'], $error['line'], $error['message']);
            }

            foreach ($manifest['problems'] as $problem) {
                $report->error('manifest.csv', 0, $problem);
            }

            $check = ($this->checkStagedImport)($run, $report);

            $run->update(['totals' => [
                'valid' => ! $report->hasErrors(),
                'files_read' => $staged['files'],
                'manifest' => $manifest,
                'check' => $check,
            ]]);

            Log::info("Import run {$run->id}: checked ({$check['error_count']} error(s)).");

            if ($run->dry_run) {
                $this->clearStagedRows($run);
                $run->update(['status' => ImportRun::STATUS_COMPLETED, 'stage' => null, 'completed_at' => now()]);

                return;
            }

            if ($report->hasErrors()) {
                throw new RuntimeException("{$check['error_count']} problem(s) found in the file. Nothing was imported. Check the file to see them.");
            }

            $this->startImport($run);
        } catch (RuntimeException $exception) {
            $this->failPreparedRun($run, $storedZipPath, $exception);
        } finally {
            @unlink($localZipPath);
        }
    }

    /**
     * Queue every stage for a checked run: one batch of jobs per stage (a few
     * instructors per job), the stages one after another, then FinishImportJob.
     * If a job still fails after its retries, the run is marked failed;
     * uploading the same zip again carries on (imported rows are skipped).
     */
    public function startImport(ImportRun $run): void
    {
        $chain = [];
        $stageTotals = [];

        foreach (array_keys(self::STAGES) as $stage) {
            $jobs = array_map(
                fn (array $refs) => new ImportStageJob($run, $stage, $refs),
                array_chunk($this->instructorRefsFor($run, $stage), self::INSTRUCTORS_PER_JOB),
            );

            $stageTotals[$stage] = ['jobs_total' => count($jobs), 'jobs_done' => 0];

            if ($jobs !== []) {
                $chain[] = Bus::batch($jobs)->name("Import run {$run->id}: {$stage}");
            }
        }

        $chain[] = new FinishImportJob($run);
        $run->update(['stage' => 'importing', 'stage_totals' => $stageTotals]);

        $runId = $run->id;

        Bus::chain($chain)
            ->catch(function (Throwable $exception) use ($runId): void {
                $failedRun = ImportRun::query()->find($runId);

                if ($failedRun !== null) {
                    app(StagedImportService::class)->failImport($failedRun, $exception);
                }
            })
            ->dispatch();

        Log::info("Import run {$run->id}: queued ".count($chain).' step(s).');
    }

    /**
     * Add one job's counts to its stage on the run (locked, as several jobs
     * may finish at once).
     *
     * @param  array<string, int>  $counts
     */
    public function recordStageProgress(ImportRun $run, string $stage, array $counts): void
    {
        DB::transaction(function () use ($run, $stage, $counts): void {
            $locked = ImportRun::query()->lockForUpdate()->findOrFail($run->id);
            $stageTotals = $locked->stage_totals ?? [];
            $current = $stageTotals[$stage] ?? ['jobs_total' => 0, 'jobs_done' => 0];

            foreach ($counts as $key => $count) {
                $current[$key] = ($current[$key] ?? 0) + $count;
            }

            $current['jobs_done']++;
            $stageTotals[$stage] = $current;

            $locked->update(['stage' => $stage, 'stage_totals' => $stageTotals]);
        });
    }

    /**
     * After every stage: complete past imported lessons still pending, clear
     * the staged rows and mark the run completed.
     */
    public function finishImport(ImportRun $run): void
    {
        $run->update(['stage' => 'finishing']);

        $completed = $this->closeOffPastLessons();
        $this->clearStagedRows($run);

        $stageTotals = $run->fresh()->stage_totals ?? [];
        $stageTotals['finish'] = ['lessons_marked_completed' => $completed];

        $run->update([
            'status' => ImportRun::STATUS_COMPLETED,
            'stage' => null,
            'stage_totals' => $stageTotals,
            'completed_at' => now(),
        ]);

        Log::info("Import run {$run->id}: completed.");
    }

    /**
     * A stage job failed after its retries: mark the run failed and clear its
     * staged rows. Everything imported so far stays (it is all add-only).
     */
    public function failImport(ImportRun $run, ?Throwable $exception): void
    {
        $run->refresh();

        if (in_array($run->status, [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING], true)) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'error' => 'Stopped during '.($run->stage ?? 'the import').': '.$this->errorText($exception, 'unknown error')
                    .' Everything imported so far is kept; upload the same zip again to carry on.',
                'completed_at' => now(),
            ]);
        }

        $this->clearStagedRows($run);
    }

    /**
     * Mark a run failed (unless it already finished), clear its staged rows
     * and remove its zip.
     */
    public function failPreparedRun(ImportRun $run, string $storedZipPath, ?Throwable $exception): void
    {
        $run->refresh();

        if (in_array($run->status, [ImportRun::STATUS_QUEUED, ImportRun::STATUS_RUNNING], true)) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'stage' => null,
                'error' => $this->errorText($exception, 'The import stopped before it finished.'),
                'completed_at' => now(),
            ]);
        }

        $this->clearStagedRows($run);
        Storage::disk(self::UPLOAD_DISK)->delete($storedZipPath);
    }

    /**
     * An exception message safe to save on the run: broken characters
     * replaced (they would make the save itself fail and leave the run stuck
     * as running) and shortened (SQL errors quote the whole statement).
     */
    protected function errorText(?Throwable $exception, string $fallback): string
    {
        $message = $exception?->getMessage() ?? $fallback;

        return mb_substr(mb_scrub($message, 'UTF-8'), 0, 1000);
    }

    /**
     * Delete a run's staged rows, in batches so a large run doesn't lock the table.
     *
     * @return int Rows deleted
     */
    public function clearStagedRows(ImportRun $run): int
    {
        $deleted = 0;

        do {
            $batch = DB::table('import_staged_rows')
                ->where('import_run_id', $run->id)
                ->limit(self::DELETE_BATCH)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
