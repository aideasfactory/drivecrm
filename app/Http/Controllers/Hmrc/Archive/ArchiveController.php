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
        return Inertia::render('Hmrc/Archive/Index', $this->indexData($request));
    }

    /**
     * Build the data array for the Archive index. Reused by InstructorController
     * when embedding the panel inside the instructor layout.
     *
     * @return array<string, mixed>
     */
    public function indexData(Request $request): array
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

        return [
            'archives' => $rows,
            'taxYears' => $this->archives->availableTaxYearsFor($instructor),
            'retentionYears' => (int) config('hmrc.year_end_archive.retention_years', 6),
            'signedUrlTtlHours' => (int) config('hmrc.year_end_archive.download_url_ttl_hours', 24),
        ];
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

        return back(fallback: route('hmrc.archive.index'))->with(
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

        $fallback = route('hmrc.archive.index');

        if (! $archive->isReady() || $archive->file_path === null) {
            return back(fallback: $fallback)
                ->with('error', 'This archive is not available — it may still be building or has been purged.');
        }

        $disk = (string) config('hmrc.year_end_archive.disk', 'local');
        if (! Storage::disk($disk)->exists($archive->file_path)) {
            return back(fallback: $fallback)
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

        return back(fallback: route('hmrc.archive.index'))->with(
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

        $fallback = route('hmrc.archive.index');

        if (! $archive->isReady()) {
            return back(fallback: $fallback)->with('error', 'Archive is not ready yet.');
        }

        app(SendArchiveReadyEmailAction::class)($archive);

        return back(fallback: $fallback)->with('success', 'Sent a fresh download link to your email.');
    }
}
