<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\HazardPerception\DeleteHazardPerceptionVideoAction;
use App\Actions\HazardPerception\GetHazardPerceptionSummaryAction;
use App\Actions\HazardPerception\GetHazardPerceptionTestAction;
use App\Actions\HazardPerception\GetHazardPerceptionTestHistoryAction;
use App\Actions\HazardPerception\GetHazardPerceptionVideosAction;
use App\Actions\HazardPerception\GetHazardPerceptionVideosForAdminAction;
use App\Actions\HazardPerception\RecordHazardPerceptionAttemptAction;
use App\Actions\HazardPerception\StartHazardPerceptionTestAction;
use App\Actions\HazardPerception\SubmitHazardPerceptionTestVideoAction;
use App\Actions\HazardPerception\UpdateHazardPerceptionVideoAction;
use App\Actions\HazardPerception\UploadHazardPerceptionVideoAction;
use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionTest;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class HazardPerceptionService extends BaseService
{
    public function __construct(
        protected GetHazardPerceptionVideosAction $getVideos,
        protected RecordHazardPerceptionAttemptAction $recordAttempt,
        protected GetHazardPerceptionSummaryAction $getSummary,
        protected StartHazardPerceptionTestAction $startTest,
        protected SubmitHazardPerceptionTestVideoAction $submitTestVideo,
        protected GetHazardPerceptionTestAction $getTest,
        protected GetHazardPerceptionTestHistoryAction $getTestHistory,
        protected GetHazardPerceptionVideosForAdminAction $getVideosForAdmin,
        protected UploadHazardPerceptionVideoAction $uploadVideo,
        protected UpdateHazardPerceptionVideoAction $updateVideo,
        protected DeleteHazardPerceptionVideoAction $deleteVideo,
    ) {}

    public function getVideos(?string $category = null): Collection
    {
        $key = $this->cacheKey('hazard_perception', 'videos', $category ?? 'all');

        return $this->remember($key, fn () => ($this->getVideos)($category));
    }

    /**
     * Record a practice attempt (not part of a test session).
     *
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     */
    public function recordAttempt(
        Student $student,
        HazardPerceptionVideo $video,
        array $taps,
    ): HazardPerceptionAttempt {
        $result = ($this->recordAttempt)($student, $video, $taps);
        $result->setRelation('video', $video);

        $this->invalidateSummaryCache($student->id);

        return $result;
    }

    public function getSummary(Student $student, ?string $category = null): array
    {
        $key = $this->cacheKey('student', $student->id, 'hazard_perception_summary'.($category ? ":{$category}" : ''));

        return $this->remember($key, fn () => ($this->getSummary)($student, $category));
    }

    public function startTest(Student $student, ?string $topic = null): HazardPerceptionTest
    {
        return ($this->startTest)($student, $topic);
    }

    /**
     * @param  array<int, float>  $taps  All tap timestamps (seconds into video)
     * @return array{attempt: HazardPerceptionAttempt, test: HazardPerceptionTest}
     */
    public function submitTestVideo(
        Student $student,
        HazardPerceptionTest $test,
        HazardPerceptionVideo $video,
        array $taps,
    ): array {
        $result = ($this->submitTestVideo)($student, $test, $video, $taps);

        $this->invalidateSummaryCache($student->id);

        return $result;
    }

    public function getTest(HazardPerceptionTest $test): HazardPerceptionTest
    {
        return ($this->getTest)($test);
    }

    public function getTestHistory(Student $student, ?string $topic = null, int $perPage = 20): LengthAwarePaginator
    {
        return ($this->getTestHistory)($student, $topic, $perPage);
    }

    /**
     * Flat list of all videos with scoring zones for the admin screen.
     *
     * @return Collection<int, HazardPerceptionVideo>
     */
    public function getVideosForAdmin(): Collection
    {
        return ($this->getVideosForAdmin)();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVideo(
        array $data,
        UploadedFile $videoFile,
        ?UploadedFile $thumbnailFile = null,
        ?UploadedFile $recapVideoFile = null,
    ): HazardPerceptionVideo {
        $video = ($this->uploadVideo)($data, $videoFile, $thumbnailFile, $recapVideoFile);

        $this->invalidateVideosCache();

        return $video;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVideo(
        HazardPerceptionVideo $video,
        array $data,
        ?UploadedFile $videoFile = null,
        ?UploadedFile $thumbnailFile = null,
        ?UploadedFile $recapVideoFile = null,
    ): HazardPerceptionVideo {
        $video = ($this->updateVideo)($video, $data, $videoFile, $thumbnailFile, $recapVideoFile);

        $this->invalidateVideosCache();

        return $video;
    }

    public function deleteVideo(HazardPerceptionVideo $video): void
    {
        ($this->deleteVideo)($video);

        $this->invalidateVideosCache();
    }

    public function invalidateVideosCache(): void
    {
        $this->invalidate([
            $this->cacheKey('hazard_perception', 'videos', 'all'),
            $this->cacheKey('hazard_perception', 'videos', 'Car'),
            $this->cacheKey('hazard_perception', 'videos', 'ADI'),
            $this->cacheKey('hazard_perception', 'videos', 'Motorcycle'),
            $this->cacheKey('hazard_perception', 'videos', 'LGV-PCV'),
        ]);
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
