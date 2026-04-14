<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\HazardPerception\GetHazardPerceptionSummaryAction;
use App\Actions\HazardPerception\GetHazardPerceptionVideosAction;
use App\Actions\HazardPerception\RecordHazardPerceptionAttemptAction;
use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Support\Collection;

class HazardPerceptionService extends BaseService
{
    public function __construct(
        protected GetHazardPerceptionVideosAction $getVideos,
        protected RecordHazardPerceptionAttemptAction $recordAttempt,
        protected GetHazardPerceptionSummaryAction $getSummary,
    ) {}

    public function getVideos(?string $category = null): Collection
    {
        $key = $this->cacheKey('hazard_perception', 'videos', $category ?? 'all');

        return $this->remember($key, fn () => ($this->getVideos)($category));
    }

    /**
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     */
    public function recordAttempt(
        Student $student,
        HazardPerceptionVideo $video,
        array $taps,
    ): HazardPerceptionAttempt {
        $result = ($this->recordAttempt)($student, $video, $taps);

        $this->invalidateSummaryCache($student->id);

        return $result;
    }

    public function getSummary(Student $student, ?string $category = null): array
    {
        $key = $this->cacheKey('student', $student->id, 'hazard_perception_summary'.($category ? ":{$category}" : ''));

        return $this->remember($key, fn () => ($this->getSummary)($student, $category));
    }

    public function invalidateSummaryCache(int $studentId): void
    {
        $this->invalidate([
            $this->cacheKey('student', $studentId, 'hazard_perception_summary'),
            $this->cacheKey('student', $studentId, 'hazard_perception_summary:Car'),
            $this->cacheKey('student', $studentId, 'hazard_perception_summary:ADI'),
            $this->cacheKey('student', $studentId, 'hazard_perception_summary:Motorcycle'),
            $this->cacheKey('student', $studentId, 'hazard_perception_summary:LGV-PCV'),
        ]);
    }
}
