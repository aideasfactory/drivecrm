<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportBundleRequest;
use App\Models\ImportRun;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Owner-only Data Import page: download the template, check a bundle zip,
 * then import it. The uploaded zip is never stored — the browser sends it for
 * the check and again for the import.
 */
class ImportController extends Controller
{
    public function __construct(
        protected ImportService $importService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Imports/Index');
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
        ]);
    }

    /**
     * Validate and import an uploaded bundle. Nothing is written unless the
     * whole bundle is valid. No emails are sent.
     */
    public function store(ImportBundleRequest $request): JsonResponse
    {
        set_time_limit(300);

        try {
            $zipPath = $this->uploadedZipPath($request);
            $result = $this->importService->checkBundle($zipPath);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $this->checkFailureMessage($exception)], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not check the file. Nothing was imported.',
            ], 500);
        }

        if ($result['errors'] !== []) {
            return response()->json([
                'message' => 'The file has problems. Nothing was imported.',
                'row_counts' => $result['row_counts'],
                'errors' => $result['errors'],
            ], 422);
        }

        $import = $this->importService->importBundle(
            $result['bundle'],
            $zipPath,
            ImportRun::SOURCE_UPLOAD,
            $request->user()->id,
            $request->file('file')->getClientOriginalName(),
        );

        return response()->json([
            'message' => 'Import complete. No emails have been sent.',
            'import_run_id' => $import['run']->id,
            'totals' => $import['totals'],
        ]);
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
