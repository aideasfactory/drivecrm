<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportBundleRequest;
use App\Http\Requests\ImportWelcomeEmailsRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use App\Support\ImportValues;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Owner-only Data Import page: download the template, check a bundle zip,
 * then import it. The check reads the upload in the request; the import
 * stores the zip just long enough for ImportBundleJob to run it.
 */
class ImportController extends Controller
{
    private const MEMORY_LIMIT = '1024M';

    public function __construct(
        protected ImportService $importService,
    ) {}

    public function index(): Response
    {
        $latestRun = ImportRun::query()->latest('id')->first();

        return Inertia::render('Imports/Index', [
            'latestRun' => $latestRun ? $this->runPayload($latestRun) : null,
        ]);
    }

    /**
     * Download the export spec + example CSVs as a zip.
     */
    public function template(): BinaryFileResponse
    {
        return response()
            ->download($this->importService->buildTemplate(), 'drive-crm-import-template.zip')
            ->deleteFileAfterSend();
    }

    /**
     * Validate an uploaded bundle without writing anything.
     */
    public function check(ImportBundleRequest $request): JsonResponse
    {
        set_time_limit(300);
        $this->raiseMemoryLimit();

        try {
            $result = $this->importService->checkBundle($this->uploadedZipPath($request));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $this->checkFailureMessage($exception)], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not check the file. Try again, or use the template zip.',
            ], 500);
        }

        return response()->json([
            'valid' => $result['errors'] === [],
            'row_counts' => $result['row_counts'],
            'errors' => $result['errors'],
            'skipped' => $result['skipped'],
        ]);
    }

    /**
     * Queue an uploaded bundle for import. The page has already checked it;
     * the job checks it again before writing anything. No emails are sent.
     *
     * Imports run one at a time in the background — a second upload waits
     * behind the first — and re-running skips everything already imported.
     */
    public function store(ImportBundleRequest $request): JsonResponse
    {
        try {
            $run = $this->importService->queueImport($request->file('file'), $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not start the import. Try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Import started. It runs in the background — you can leave this page.',
            'run' => $this->runPayload($run),
        ], 202);
    }

    /**
     * Welcome emails for a CSV of instructor emails: preview who would be
     * emailed, then (with `send`) queue the emails. Only imported instructors
     * still waiting for theirs are emailed — never their students, never twice.
     */
    public function welcomeEmails(ImportWelcomeEmailsRequest $request): JsonResponse
    {
        $emails = ImportValues::emails((string) file_get_contents($request->file('file')->getRealPath()));

        if ($emails === []) {
            return response()->json(['message' => 'No email addresses found in the file.'], 422);
        }

        $preview = $this->importService->previewInstructorWelcomeEmails($emails);

        $sent = 0;
        $failed = [];

        if ($request->boolean('send')) {
            foreach ($preview['pending'] as $user) {
                if ($this->importService->sendWelcomeEmail($user)) {
                    $sent++;
                } else {
                    $failed[] = $user->email;
                }
            }
        }

        return response()->json([
            'pending' => $preview['pending']
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                ->values(),
            'already_sent' => $preview['already_sent'],
            'not_instructors' => $preview['not_instructors'],
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    /**
     * Progress of one import run, polled by the Data Import page.
     */
    public function show(ImportRun $importRun): JsonResponse
    {
        return response()->json(['run' => $this->runPayload($importRun)]);
    }

    /**
     * @return array{id: int, status: string, file_name: string|null, totals: array<string, int>|null, error: string|null, created_at: string|null, completed_at: string|null}
     */
    private function runPayload(ImportRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'file_name' => $run->file_name,
            'totals' => $run->totals,
            'error' => $run->error,
            'created_at' => $run->created_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];
    }

    /**
     * A bundle's rows are all held in memory while it is checked and
     * imported, which outgrows the default limit for large diaries.
     */
    private function raiseMemoryLimit(): void
    {
        ini_set('memory_limit', self::MEMORY_LIMIT);
    }

    /**
     * Filesystem path of the uploaded zip.
     *
     * realpath() returns false for a valid upload when the temp file cannot
     * be resolved (open_basedir, or the path is not yet canonical). Passing
     * that false into the checker throws before any result is returned.
     *
     * @throws RuntimeException
     */
    private function uploadedZipPath(ImportBundleRequest $request): string
    {
        $uploaded = $request->file('file');

        if ($uploaded === null) {
            throw new RuntimeException('Please choose a zip file to upload.');
        }

        $path = $uploaded->getRealPath();

        if (! is_string($path) || $path === '') {
            $path = $uploaded->getPathname();
        }

        if ($path === '') {
            throw new RuntimeException('Could not read the uploaded zip.');
        }

        return $path;
    }

    /**
     * Message safe to show on the Data Import page.
     */
    private function checkFailureMessage(RuntimeException $exception): string
    {
        if (str_starts_with($exception->getMessage(), 'Could not open zip:')) {
            return 'Could not open the zip. Make sure it is a valid .zip of the import CSVs.';
        }

        return $exception->getMessage();
    }
}
