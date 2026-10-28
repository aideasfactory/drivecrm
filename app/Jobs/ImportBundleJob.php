<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\ImportService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs a Data Import upload in the background.
 *
 * Imports run one at a time: a second upload waits in the queue until the
 * first finishes, because two overlapping runs can both decide a row is new
 * and insert it twice. Re-running is safe — rows already imported are skipped.
 */
class ImportBundleJob implements ShouldQueue
{
    use Queueable;

    public const OVERLAP_KEY = 'data-import';

    public int $timeout = 3600;

    /** Waiting behind another import counts as an attempt, so retry by time; a real error fails at once. */
    public int $maxExceptions = 1;

    public function __construct(
        public ImportRun $run,
        public string $storedZipPath,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(self::OVERLAP_KEY))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 300),
        ];
    }

    /**
     * Free the one-at-a-time lock left behind when a worker dies mid-import
     * (it otherwise holds until expireAfter). Used by `import:reset`.
     */
    public static function releaseOverlapLock(): void
    {
        $key = (new WithoutOverlapping(self::OVERLAP_KEY))->getLockKey(new self(new ImportRun, ''));

        Cache::lock($key)->forceRelease();
    }

    public function handle(ImportService $importService): void
    {
        $importService->runQueuedImport($this->run, $this->storedZipPath);
    }

    public function failed(?Throwable $exception): void
    {
        app(ImportService::class)->failQueuedImport($this->run, $this->storedZipPath, $exception);
    }
}
