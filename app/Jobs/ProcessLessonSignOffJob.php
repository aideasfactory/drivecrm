<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Services\LessonSignOffService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLessonSignOffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    public function __construct(
        public Lesson $lesson,
        public Instructor $instructor
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LessonSignOffService $service): void
    {
        try {
            $result = $service->signOffLesson($this->lesson, $this->instructor);

            Log::info('Lesson sign-off processed successfully', [
                'lesson_id' => $this->lesson->id,
                'instructor_id' => $this->instructor->id,
                'payout_id' => $result['payout']->id,
                'order_completed' => $result['order_completed'],
            ]);
        } catch (\Exception $e) {
            Log::error('Lesson sign-off failed', [
                'lesson_id' => $this->lesson->id,
                'instructor_id' => $this->instructor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Lesson sign-off job failed after all retries', [
            'lesson_id' => $this->lesson->id,
            'instructor_id' => $this->instructor->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
