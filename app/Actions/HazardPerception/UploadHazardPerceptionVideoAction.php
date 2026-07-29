<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadHazardPerceptionVideoAction
{
    /**
     * Upload the clip (and optional thumbnail / recap video) to S3, then
     * create the video record with its scoring zones in one transaction.
     * Uploaded files are removed again if the database write fails.
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
        array $data,
        UploadedFile $videoFile,
        ?UploadedFile $thumbnailFile = null,
        ?UploadedFile $recapVideoFile = null,
    ): HazardPerceptionVideo {
        $paths = [];
        $paths['video_url'] = $videoFile->store('hazard-perception/videos', 's3');

        if ($thumbnailFile !== null) {
            $paths['thumbnail_url'] = $thumbnailFile->store('hazard-perception/thumbnails', 's3');
        }

        if ($recapVideoFile !== null) {
            $paths['recap_video_url'] = $recapVideoFile->store('hazard-perception/recaps', 's3');
        }

        try {
            return DB::transaction(function () use ($data, $paths): HazardPerceptionVideo {
                $video = HazardPerceptionVideo::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'category' => $data['category'],
                    'topic' => $data['topic'],
                    'duration_seconds' => $data['duration_seconds'],
                    'is_double_hazard' => $data['is_double_hazard'],
                    'has_recap' => $data['has_recap'],
                    'video_url' => $paths['video_url'],
                    'thumbnail_url' => $paths['thumbnail_url'] ?? null,
                    'recap_video_url' => $paths['recap_video_url'] ?? null,
                ]);

                $video->scoringZones()->createMany($data['zones']);

                return $video->load('scoringZones');
            });
        } catch (Throwable $exception) {
            Storage::disk('s3')->delete(array_values($paths));

            throw $exception;
        }
    }
}
