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
            $result = $this->importService->checkBundle($request->file('file')->getRealPath());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
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

        $zipPath = $request->file('file')->getRealPath();

        try {
            $result = $this->importService->checkBundle($zipPath);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
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
}
