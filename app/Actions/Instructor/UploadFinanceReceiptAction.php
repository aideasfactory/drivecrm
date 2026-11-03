<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\ReceiptStatus;
use App\Jobs\ProcessFinanceReceiptImageJob;
use App\Models\InstructorFinance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadFinanceReceiptAction
{
    public function __construct(
        protected MarkFinanceReceiptReadyAction $markFinanceReceiptReady
    ) {}

    /**
     * Upload a receipt to the private S3 disk and attach it to a finance record.
     * Replaces any existing receipt (and its thumbnail) on the same record. The
     * old files are only deleted once the new one is stored, so a failed upload
     * keeps the old receipt.
     *
     * JPEG/PNG photos are stored as uploaded with status `processing` and shrunk
     * by ProcessFinanceReceiptImageJob, which also creates the thumbnail. PDFs are
     * stored as uploaded and are `ready` straight away.
     */
    public function __invoke(InstructorFinance $finance, UploadedFile $file): InstructorFinance
    {
        $previousPaths = array_filter([$finance->receipt_path, $finance->receipt_thumbnail_path]);

        $directory = "instructors/{$finance->instructor_id}/finance-receipts/{$finance->id}";
        $path = $file->store($directory, 's3');

        if ($path === false) {
            throw new \RuntimeException("Failed to store receipt for finance record {$finance->id}.");
        }

        $mimeType = $file->getMimeType();
        $needsProcessing = ProcessFinanceReceiptImageAction::isProcessable($mimeType);

        $finance->update([
            'receipt_path' => $path,
            'receipt_original_name' => $file->getClientOriginalName(),
            'receipt_mime_type' => $mimeType,
            'receipt_size_bytes' => $file->getSize(),
            'receipt_thumbnail_path' => null,
            'receipt_status' => $needsProcessing ? ReceiptStatus::Processing : ReceiptStatus::Ready,
        ]);

        $stalePaths = array_values(array_diff($previousPaths, [$path]));

        if ($stalePaths !== []) {
            Storage::disk('s3')->delete($stalePaths);
        }

        if ($needsProcessing) {
            $this->queueProcessing($finance->id, $path);
        }

        return $finance->fresh();
    }

    /**
     * Queue the shrink once any open transaction commits. If the job can't be
     * queued, the original is kept as the final receipt rather than leaving it
     * stuck in `processing`.
     */
    private function queueProcessing(int $financeId, string $path): void
    {
        try {
            ProcessFinanceReceiptImageJob::dispatch($financeId, $path)->afterCommit();
        } catch (Throwable $exception) {
            Log::error('Could not queue finance receipt image processing; keeping the original', [
                'finance_id' => $financeId,
                'source_path' => $path,
                'error' => $exception->getMessage(),
            ]);

            ($this->markFinanceReceiptReady)($financeId, $path);
        }
    }
}
