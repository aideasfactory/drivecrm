<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadInstructorProfilePictureAction
{
    /**
     * Upload a profile picture to S3 and update the instructor record.
     */
    public function __invoke(Instructor $instructor, UploadedFile $file): Instructor
    {
        // Delete old profile picture if exists
        if ($instructor->profile_picture_path) {
            Storage::disk('s3')->delete($instructor->profile_picture_path);
        }

        $path = $file->store("instructors/{$instructor->id}/profile", 's3');

        $instructor->update(['profile_picture_path' => $path]);

        return $instructor->fresh();
    }
}
