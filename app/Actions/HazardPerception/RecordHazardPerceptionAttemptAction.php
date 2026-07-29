<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionScoringZone;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Support\Collection;

class RecordHazardPerceptionAttemptAction
{
    /**
     * Accept an array of raw tap timestamps from the mobile app,
     * match the best tap to each hazard's scoring zones, and calculate scores.
     *
     * Scoring algorithm:
     * - Each hazard has explicit scoring zones (one time block per score, 5 → 1).
     * - A tap inside a zone scores that zone's points.
     * - A tap outside every zone scores 0.
     * - For each hazard, the best-scoring tap is used.
     *
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     * @param  int|null  $testId  Links the attempt to a test session (null = practice)
     */
    public function __invoke(
        Student $student,
        HazardPerceptionVideo $video,
        array $taps,
        ?int $testId = null,
    ): HazardPerceptionAttempt {
        $sortedTaps = $taps;
        sort($sortedTaps);

        $zonesByHazard = $video->scoringZones()->get()->groupBy('hazard_number');

        [$h1ResponseTime, $h1Score] = $this->findBestTapForHazard(
            $sortedTaps,
            $zonesByHazard->get(1, collect()),
        );

        $h2ResponseTime = null;
        $h2Score = null;

        if ($video->is_double_hazard && $zonesByHazard->has(2)) {
            [$h2ResponseTime, $h2Score] = $this->findBestTapForHazard(
                $sortedTaps,
                $zonesByHazard->get(2),
            );
        }

        $totalScore = $h1Score + ($h2Score ?? 0);

        return HazardPerceptionAttempt::create([
            'student_id' => $student->id,
            'hazard_perception_video_id' => $video->id,
            'hazard_perception_test_id' => $testId,
            'hazard_1_response_time' => $h1ResponseTime,
            'hazard_1_score' => $h1Score,
            'hazard_2_response_time' => $h2ResponseTime,
            'hazard_2_score' => $h2Score,
            'total_score' => $totalScore,
            'completed_at' => now(),
        ]);
    }

    /**
     * Find the best-scoring tap across a hazard's scoring zones.
     *
     * @param  array<int, float>  $taps  Sorted tap timestamps
     * @param  Collection<int, HazardPerceptionScoringZone>  $zones
     * @return array{0: float|null, 1: int} [response_time, score]
     */
    private function findBestTapForHazard(array $taps, Collection $zones): array
    {
        $bestTime = null;
        $bestScore = 0;

        foreach ($taps as $tap) {
            $score = $this->scoreForTap($tap, $zones);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTime = $tap;
            }
        }

        return [$bestTime, $bestScore];
    }

    /**
     * @param  Collection<int, HazardPerceptionScoringZone>  $zones
     */
    private function scoreForTap(float $tapTime, Collection $zones): int
    {
        $best = 0;

        foreach ($zones as $zone) {
            if ($tapTime >= (float) $zone->start_seconds
                && $tapTime <= (float) $zone->end_seconds
                && $zone->score > $best) {
                $best = $zone->score;
            }
        }

        return $best;
    }
}
