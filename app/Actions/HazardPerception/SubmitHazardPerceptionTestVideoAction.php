<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionTest;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class SubmitHazardPerceptionTestVideoAction
{
    public function __construct(
        protected RecordHazardPerceptionAttemptAction $recordAttempt,
    ) {}

    /**
     * Score one video within a test session. Submitting the final video
     * completes the test and rolls up its total score.
     *
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     * @return array{attempt: HazardPerceptionAttempt, test: HazardPerceptionTest}
     */
    public function __invoke(
        Student $student,
        HazardPerceptionTest $test,
        HazardPerceptionVideo $video,
        array $taps,
    ): array {
        if (! $test->videos()->whereKey($video->id)->exists()) {
            throw ValidationException::withMessages([
                'video' => 'This video is not part of the test.',
            ]);
        }

        if ($test->attempts()->where('hazard_perception_video_id', $video->id)->exists()) {
            throw ValidationException::withMessages([
                'video' => 'This video has already been completed in this test.',
            ]);
        }

        $attempt = ($this->recordAttempt)($student, $video, $taps, $test->id);
        $attempt->setRelation('video', $video);

        if ($test->attempts()->count() >= $test->total_videos) {
            $test->update([
                'total_score' => (int) $test->attempts()->sum('total_score'),
                'completed_at' => now(),
            ]);
        }

        return [
            'attempt' => $attempt,
            'test' => $test->load(['videos', 'attempts']),
        ];
    }
}
