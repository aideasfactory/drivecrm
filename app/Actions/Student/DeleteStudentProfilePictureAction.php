<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class DeleteStudentProfilePictureAction
{
    /**
     * Delete the student's profile picture from S3 and clear the record.
     */
    public function __invoke(Student $student): Student
    {
        if ($student->profile_picture_path) {
            Storage::disk('s3')->delete($student->profile_picture_path);
            $student->update(['profile_picture_path' => null]);
        }

        return $student->fresh();
    }
}
