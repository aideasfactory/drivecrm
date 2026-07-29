<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeleteHazardPerceptionVideoAction
{
    /**
     * Delete a video record (zones and attempts cascade) and remove any
     * uploaded S3 objects. Legacy full-URL values are left untouched.
     */
    public function __invoke(HazardPerceptionVideo $video): void
    {
        $paths = array_filter(
            [$video->video_url, $video->thumbnail_url, $video->recap_video_url],
            fn (?string $path): bool => $path !== null && ! Str::startsWith($path, ['http://', 'https://']),
        );

        $video->delete();

        if ($paths !== []) {
            Storage::disk('s3')->delete(array_values($paths));
        }
    }
}
