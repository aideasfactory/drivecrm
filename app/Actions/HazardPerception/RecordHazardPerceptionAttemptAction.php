<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;

class RecordHazardPerceptionAttemptAction
{
    /**
     * Accept an array of raw tap timestamps from the mobile app,
     * match the best tap to each hazard window, and calculate scores.
     *
     * Scoring algorithm:
     * - The scoring window runs from hazard_X_start to hazard_X_end.
     * - A tap exactly at hazard_X_start scores 5 (perfect).
     * - Score decreases linearly as the tap moves further from the start
     *   toward the end of the window: 5 → 4 → 3 → 2 → 1.
     * - A tap outside the window scores 0.
     * - For each hazard, the best-scoring tap within the window is used.
     *
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     */
    public function __invoke(
        Student $student,
        HazardPerceptionVideo $video,
        array $taps,
    ): HazardPerceptionAttempt {
        $sortedTaps = $taps;
        sort($sortedTaps);

        [$h1ResponseTime, $h1Score] = $this->findBestTapForHazard(
            $sortedTaps,
            (float) $video->hazard_1_start,
            (float) $video->hazard_1_end,
        );

        $h2ResponseTime = null;
        $h2Score = null;

        if ($video->is_double_hazard && $video->hazard_2_start !== null) {
            [$h2ResponseTime, $h2Score] = $this->findBestTapForHazard(
                $sortedTaps,
                (float) $video->hazard_2_start,
                (float) $video->hazard_2_end,
            );
        }

        $totalScore = $h1Score + ($h2Score ?? 0);

        return HazardPerceptionAttempt::create([
            'student_id' => $student->id,
            'hazard_perception_video_id' => $video->id,
            'hazard_1_response_time' => $h1ResponseTime,
            'hazard_1_score' => $h1Score,
            'hazard_2_response_time' => $h2ResponseTime,
            'hazard_2_score' => $h2Score,
            'total_score' => $totalScore,
            'completed_at' => now(),
        ]);
    }

    /**
     * Find the best-scoring tap within a hazard window.
     *
     * @param  array<int, float>  $taps  Sorted tap timestamps
     * @return array{0: float|null, 1: int} [response_time, score]
     */
    private function findBestTapForHazard(array $taps, float $windowStart, float $windowEnd): array
    {
        $bestTime = null;
        $bestScore = 0;

        foreach ($taps as $tap) {
            if ($tap > $windowEnd) {
                break;
            }

            if ($tap < $windowStart) {
                continue;
            }

            $score = $this->calculateScore($tap, $windowStart, $windowEnd);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTime = $tap;
            }
        }

        return [$bestTime, $bestScore];
    }

    /**
     * Calculate score based on how close the tap is to the hazard start.
     *
     * The window is divided into 5 equal bands:
     *   0%–20%  elapsed → 5 points (closest to hazard appearing)
     *   20%–40% elapsed → 4 points
     *   40%–60% elapsed → 3 points
     *   60%–80% elapsed → 2 points
     *   80%–100% elapsed → 1 point
     */
    private function calculateScore(float $tapTime, float $windowStart, float $windowEnd): int
    {
        $windowDuration = $windowEnd - $windowStart;

        if ($windowDuration <= 0) {
            return 0;
        }

        $elapsed = $tapTime - $windowStart;
        $fraction = $elapsed / $windowDuration;

        return match (true) {
            $fraction <= 0.2 => 5,
            $fraction <= 0.4 => 4,
            $fraction <= 0.6 => 3,
            $fraction <= 0.8 => 2,
            default => 1,
        };
    }
}
