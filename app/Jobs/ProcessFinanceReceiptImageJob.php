<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\InstructorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shrinks an uploaded receipt photo and creates its thumbnail in the background,
 * so the app can upload the untouched camera file without processing it on the
 * phone. Takes ids rather than the model so a deleted record just skips.
 *
 * Decoding a large camera photo can need 200–300 MB, so the queue worker's
 * memory limit must allow for it.
 */
class ProcessFinanceReceiptImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /** Must stay below the database queue's `retry_after` (90s) so a slow run isn't handed out twice. */
    public int $timeout = 60;

    public function __construct(
        public int $financeId,
        public string $sourcePath,
    ) {}

    /** PHP memory needed to decode a large camera photo (Imagick also allocates outside PHP). */
    private const MEMORY_LIMIT = '512M';

    public function handle(InstructorService $instructorService): void
    {
        $this->raiseMemoryLimit();

        $swapped = $instructorService->processFinanceReceiptImage($this->financeId, $this->sourcePath);

        Log::info($swapped ? 'Finance receipt image processed' : 'Finance receipt image processing skipped (receipt changed)', [
            'finance_id' => $this->financeId,
            'source_path' => $this->sourcePath,
        ]);
    }

    /**
     * Raise PHP's memory limit for this worker when it's below what decoding needs.
     * Never lowers it, and leaves an unlimited (-1) setting alone.
     */
    private function raiseMemoryLimit(): void
    {
        $current = (string) ini_get('memory_limit');

        if ($current === '-1' || $this->toBytes($current) >= $this->toBytes(self::MEMORY_LIMIT)) {
            return;
        }

        ini_set('memory_limit', self::MEMORY_LIMIT);
    }

    private function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Out of retries: keep the original upload as the receipt and mark it ready.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Finance receipt image processing failed; keeping the original', [
            'finance_id' => $this->financeId,
            'source_path' => $this->sourcePath,
            'error' => $exception->getMessage(),
        ]);

        app(InstructorService::class)->markFinanceReceiptReady($this->financeId, $this->sourcePath);
    }
}
