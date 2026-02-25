<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Student\Lesson\GetAllResourceTagsAction;
use App\Actions\Student\Lesson\GetRecommendedResourcesAction;
use App\Actions\Student\Lesson\MatchResourcesWithAiAction;
use App\Mail\LessonResourceRecommendations;
use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessResourceRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 2;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function __construct(
        public Lesson $lesson
    ) {}

    /**
     * Execute the job: fetch tags, run AI matching, send email with recommended resources.
     */
    public function handle(
        GetAllResourceTagsAction $getAllResourceTags,
        MatchResourcesWithAiAction $matchResourcesWithAi,
        GetRecommendedResourcesAction $getRecommendedResources
    ): void {
        $this->lesson->load(['order.student', 'instructor.user']);

        $student = $this->lesson->order?->student;

        if (! $student) {
            Log::warning('ProcessResourceRecommendationsJob: No student found for lesson', [
                'lesson_id' => $this->lesson->id,
            ]);

            return;
        }

        // 1. Fetch all unique tags from the resources table
        $availableTags = ($getAllResourceTags)();

        if (empty($availableTags)) {
            Log::info('ProcessResourceRecommendationsJob: No resource tags in database, skipping', [
                'lesson_id' => $this->lesson->id,
            ]);

            return;
        }

        // 2. Run AI matching to rank tags by relevance to the lesson summary
        $summary = $this->lesson->summary ?? '';

        if (empty(trim($summary))) {
            Log::info('ProcessResourceRecommendationsJob: No lesson summary, skipping', [
                'lesson_id' => $this->lesson->id,
            ]);

            return;
        }

        $rankedTags = ($matchResourcesWithAi)($summary, $availableTags);

        if (empty($rankedTags)) {
            Log::info('ProcessResourceRecommendationsJob: AI returned no matching tags, skipping email', [
                'lesson_id' => $this->lesson->id,
            ]);

            return;
        }

        // 3. Query resources matching the top-ranked tags (up to 5)
        $resources = ($getRecommendedResources)($rankedTags);

        if ($resources->isEmpty()) {
            Log::info('ProcessResourceRecommendationsJob: No resources found for matched tags, skipping email', [
                'lesson_id' => $this->lesson->id,
                'matched_tags' => $rankedTags,
            ]);

            return;
        }

        // 4. Send the resource recommendations email
        $recipientEmail = $student->email ?? $student->user?->email;

        if (! $recipientEmail) {
            Log::warning('ProcessResourceRecommendationsJob: No email address for student', [
                'lesson_id' => $this->lesson->id,
                'student_id' => $student->id,
            ]);

            return;
        }

        Mail::to($recipientEmail)->queue(
            new LessonResourceRecommendations($this->lesson, $student, $resources)
        );

        Log::info('ProcessResourceRecommendationsJob: Resource recommendations email queued', [
            'lesson_id' => $this->lesson->id,
            'student_id' => $student->id,
            'resources_count' => $resources->count(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessResourceRecommendationsJob failed', [
            'lesson_id' => $this->lesson->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
