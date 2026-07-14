<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionTest;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class StartHazardPerceptionTestAction
{
    /**
     * Create a hazard perception test session with a random selection of
     * videos (optionally filtered by topic). The selection and playback
     * order are persisted so the test can be resumed and cannot be
     * re-rolled. If the bank holds fewer videos than the configured count,
     * the test uses every available video.
     */
    public function __invoke(Student $student, ?string $topic = null): HazardPerceptionTest
    {
        $count = (int) config('hazard_perception.videos_per_test');

        $videos = HazardPerceptionVideo::query()
            ->when($topic, fn ($q) => $q->where('topic', $topic))
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($videos->isEmpty()) {
            throw ValidationException::withMessages([
                'topic' => $topic
                    ? "No hazard perception videos are available for the topic '{$topic}'."
                    : 'No hazard perception videos are available.',
            ]);
        }

        $test = HazardPerceptionTest::create([
            'student_id' => $student->id,
            'topic' => $topic,
            'total_videos' => $videos->count(),
            'max_score' => $videos->sum(fn (HazardPerceptionVideo $video): int => $video->is_double_hazard ? 10 : 5),
            'started_at' => now(),
        ]);

        $test->videos()->attach(
            $videos->values()
                ->mapWithKeys(fn (HazardPerceptionVideo $video, int $index) => [$video->id => ['position' => $index + 1]])
                ->all(),
        );

        return $test->load(['videos', 'attempts']);
    }
}
