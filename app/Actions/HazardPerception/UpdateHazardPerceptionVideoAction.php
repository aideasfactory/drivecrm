<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateHazardPerceptionVideoAction
{
    /**
     * Update a video's metadata and scoring zones, optionally replacing the
     * uploaded files. Replaced S3 objects are deleted after a successful save.
     *
     * @param array{
     *     title: string,
     *     description: string|null,
     *     category: string,
     *     topic: string,
     *     duration_seconds: int,
     *     is_double_hazard: bool,
     *     has_recap: bool,
     *     zones: array<int, array{hazard_number: int, score: int, start_seconds: float, end_seconds: float}>
     * } $data
     */
    public function __invoke(
        HazardPerceptionVideo $video,
        array $data,
        ?UploadedFile $videoFile = null,
        ?UploadedFile $thumbnailFile = null,
        ?UploadedFile $recapVideoFile = null,
    ): HazardPerceptionVideo {
        $replacedPaths = [];

        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'topic' => $data['topic'],
            'duration_seconds' => $data['duration_seconds'],
            'is_double_hazard' => $data['is_double_hazard'],
            'has_recap' => $data['has_recap'],
        ];

        if ($videoFile !== null) {
            $replacedPaths[] = $video->video_url;
            $attributes['video_url'] = $videoFile->store('hazard-perception/videos', 's3');
        }

        if ($thumbnailFile !== null) {
            $replacedPaths[] = $video->thumbnail_url;
            $attributes['thumbnail_url'] = $thumbnailFile->store('hazard-perception/thumbnails', 's3');
        }

        if ($recapVideoFile !== null) {
            $replacedPaths[] = $video->recap_video_url;
            $attributes['recap_video_url'] = $recapVideoFile->store('hazard-perception/recaps', 's3');
        }

        DB::transaction(function () use ($video, $attributes, $data): void {
            $video->update($attributes);
            $video->scoringZones()->delete();
            $video->scoringZones()->createMany($data['zones']);
        });

        $this->deleteStoredFiles($replacedPaths);

        return $video->refresh()->load('scoringZones');
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        $deletable = array_filter(
            $paths,
            fn (?string $path): bool => $path !== null && ! Str::startsWith($path, ['http://', 'https://']),
        );

        if ($deletable !== []) {
            Storage::disk('s3')->delete(array_values($deletable));
        }
    }
}
