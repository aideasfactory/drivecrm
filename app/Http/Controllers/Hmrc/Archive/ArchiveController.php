<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc\Archive;

use App\Actions\YearEndArchive\SendArchiveReadyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hmrc\Archive\StoreYearEndArchiveRequest;
use App\Models\YearEndArchive;
use App\Services\YearEndArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public function __construct(
        protected YearEndArchiveService $archives,
    ) {}

    public function index(Request $request): Response
    {
        $instructor = $request->user()->instructor;

        $rows = $this->archives->archivesFor($instructor)->map(fn (YearEndArchive $a) => [
            'id' => $a->id,
            'tax_year_start' => $a->tax_year_start,
            'tax_year_label' => $a->taxYearLabel(),
            'status' => $a->status,
            'file_size_bytes' => $a->file_size_bytes,
            'counts' => $a->counts,
            'generated_at' => $a->generated_at?->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            'queued_at' => $a->queued_at?->toIso8601String(),
            'error_message' => $a->error_message,
        ])->values()->all();

        return Inertia::render('Hmrc/Archive/Index', [
            'archives' => $rows,
            'taxYears' => $this->archives->availableTaxYearsFor($instructor),
            'retentionYears' => (int) config('hmrc.year_end_archive.retention_years', 6),
            'signedUrlTtlHours' => (int) config('hmrc.year_end_archive.download_url_ttl_hours', 24),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        $validated = $request->validate([
            'tax_year_start' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        return response()->json($this->archives->summaryCountsFor($instructor, (int) $validated['tax_year_start']));
    }

    public function store(StoreYearEndArchiveRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;
        $year = (int) $request->validated('tax_year_start');

        $this->archives->queueBuild($instructor, $year);

        return redirect()->route('hmrc.archive.index')->with(
            'success',
            "Building your {$year}/".substr((string) ($year + 1), -2).' archive. You will get an email when it is ready.',
        );
    }

    public function download(Request $request, YearEndArchive $archive): StreamedResponse|RedirectResponse
    {
        // Two access paths:
        //  1. Signed URL from the Mandrill email — no session required.
        //  2. In-page download button — must be the owning instructor.
        if (! $request->hasValidSignature()) {
            $instructor = $request->user()?->instructor;
            if ($instructor === null || $archive->instructor_id !== $instructor->id) {
                abort(403);
            }
        }

        if (! $archive->isReady() || $archive->file_path === null) {
            return redirect()
                ->route('hmrc.archive.index')
                ->with('error', 'This archive is not available — it may still be building or has been purged.');
        }

        $disk = (string) config('hmrc.year_end_archive.disk', 'local');
        if (! Storage::disk($disk)->exists($archive->file_path)) {
            return redirect()
                ->route('hmrc.archive.index')
                ->with('error', 'Archive file is missing on disk. Regenerate it from the archives page.');
        }

        return Storage::disk($disk)->download(
            $archive->file_path,
            sprintf('drive-tax-archive-%s.zip', $archive->taxYearLabel()),
            ['Content-Type' => 'application/zip'],
        );
    }

    public function regenerate(Request $request, YearEndArchive $archive): RedirectResponse
    {
        $instructor = $request->user()->instructor;
        if ($archive->instructor_id !== $instructor->id) {
            abort(403);
        }

        $this->archives->queueBuild($instructor, (int) $archive->tax_year_start);

        return redirect()->route('hmrc.archive.index')->with(
            'success',
            "Regenerating your {$archive->taxYearLabel()} archive.",
        );
    }

    public function emailLink(Request $request, YearEndArchive $archive): RedirectResponse
    {
        $instructor = $request->user()->instructor;
        if ($archive->instructor_id !== $instructor->id) {
            abort(403);
        }

        if (! $archive->isReady()) {
            return redirect()->route('hmrc.archive.index')->with('error', 'Archive is not ready yet.');
        }

        app(SendArchiveReadyEmailAction::class)($archive);

        return redirect()->route('hmrc.archive.index')->with('success', 'Sent a fresh download link to your email.');
    }
}
