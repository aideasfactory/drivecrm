<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Model $loggable,
        public string $message,
        public string $category,
        public ?array $metadata = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ActivityLog::create([
                'loggable_type' => get_class($this->loggable),
                'loggable_id' => $this->loggable->id,
                'category' => $this->category,
                'message' => $this->message,
                'metadata' => $this->metadata,
            ]);

            Log::info('Activity logged', [
                'loggable_type' => get_class($this->loggable),
                'loggable_id' => $this->loggable->id,
                'category' => $this->category,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'loggable_type' => get_class($this->loggable),
                'loggable_id' => $this->loggable->id,
                'category' => $this->category,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Activity log job failed after all retries', [
            'loggable_type' => get_class($this->loggable),
            'loggable_id' => $this->loggable->id,
            'category' => $this->category,
            'error' => $exception->getMessage(),
        ]);
    }
}
