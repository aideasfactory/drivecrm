<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadStudentProfilePictureAction
{
    /**
     * Upload a profile picture to S3 and update the student record.
     */
    public function __invoke(Student $student, UploadedFile $file): Student
    {
        // Delete old profile picture if exists
        if ($student->profile_picture_path) {
            Storage::disk('s3')->delete($student->profile_picture_path);
        }

        $path = $file->storePublicly("students/{$student->id}/profile", 's3');

        $student->update(['profile_picture_path' => $path]);

        return $student->fresh();
    }
}
