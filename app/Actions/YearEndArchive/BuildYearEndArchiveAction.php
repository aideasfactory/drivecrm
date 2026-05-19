<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Models\YearEndArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuildYearEndArchiveAction
{
    public function __construct(
        private readonly WriteFinancesCsvAction $writeFinancesCsv,
        private readonly WriteMileageCsvAction $writeMileageCsv,
        private readonly CopyReceiptsToArchiveAction $copyReceipts,
        private readonly WriteSubmissionsJsonAction $writeSubmissions,
        private readonly RenderArchiveCoverSheetPdfAction $renderCoverSheet,
        private readonly BuildArchiveZipAction $buildZip,
    ) {}

    /**
     * Build the ZIP for a queued archive row. Updates the row through
     * building → ready (or failed) and returns the updated model.
     */
    public function __invoke(YearEndArchive $archive): YearEndArchive
    {
        $archive->status = YearEndArchive::STATUS_BUILDING;
        $archive->error_message = null;
        $archive->save();

        $instructor = $archive->instructor;
        $taxYearStart = $archive->taxYearStartDate();
        $taxYearEnd = $archive->taxYearEndDate();

        $stagingDir = sys_get_temp_dir().'/drivecrm-archive-'.$archive->id.'-'.uniqid('', true);
        if (! mkdir($stagingDir, 0755, true) && ! is_dir($stagingDir)) {
            $this->markFailed($archive, "Could not create staging dir: {$stagingDir}");

            return $archive;
        }

        try {
            $counts = [
                'finances' => ($this->writeFinancesCsv)($stagingDir, $instructor, $taxYearStart, $taxYearEnd),
                'mileage_logs' => ($this->writeMileageCsv)($stagingDir, $instructor, $taxYearStart, $taxYearEnd),
                'receipts' => ($this->copyReceipts)($stagingDir, $instructor, $taxYearStart, $taxYearEnd),
                'submissions' => ($this->writeSubmissions)($stagingDir, $instructor, $taxYearStart, $taxYearEnd),
            ];

            ($this->renderCoverSheet)($stagingDir, $instructor, $taxYearStart, $taxYearEnd, $counts);

            $disk = (string) config('hmrc.year_end_archive.disk', 'local');
            $relativePath = $this->resolveRelativePath($archive);
            $absolutePath = Storage::disk($disk)->path($relativePath);

            $size = ($this->buildZip)($stagingDir, $absolutePath);

            $retentionYears = (int) config('hmrc.year_end_archive.retention_years', 6);

            $archive->update([
                'status' => YearEndArchive::STATUS_READY,
                'file_path' => $relativePath,
                'file_size_bytes' => $size,
                'counts' => $counts,
                'generated_at' => Carbon::now(),
                'expires_at' => $taxYearEnd->copy()->addYears($retentionYears),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Year-end archive build failed', [
                'archive_id' => $archive->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markFailed($archive, $e->getMessage());
        } finally {
            $this->cleanupStaging($stagingDir);
        }

        return $archive->fresh() ?? $archive;
    }

    private function resolveRelativePath(YearEndArchive $archive): string
    {
        $template = (string) config('hmrc.year_end_archive.path_template', 'archives/{instructor_id}/{tax_year_start}.zip');

        return strtr($template, [
            '{instructor_id}' => (string) $archive->instructor_id,
            '{tax_year_start}' => (string) $archive->tax_year_start,
        ]);
    }

    private function markFailed(YearEndArchive $archive, string $message): void
    {
        $archive->update([
            'status' => YearEndArchive::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    private function cleanupStaging(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }
}
