<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Facades\Storage;

class DeleteInstructorProfilePictureAction
{
    /**
     * Delete the instructor's profile picture from S3 and clear the record.
     */
    public function __invoke(Instructor $instructor): Instructor
    {
        if ($instructor->profile_picture_path) {
            Storage::disk('s3')->delete($instructor->profile_picture_path);
            $instructor->update(['profile_picture_path' => null]);
        }

        return $instructor->fresh();
    }
}
