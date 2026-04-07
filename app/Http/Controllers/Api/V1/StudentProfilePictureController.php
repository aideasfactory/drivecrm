<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateStudentProfilePictureRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Support\Facades\Gate;

class StudentProfilePictureController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Upload or replace a student's profile picture.
     */
    public function store(UpdateStudentProfilePictureRequest $request, Student $student): StudentResource
    {
        Gate::authorize('update', $student);

        $student = $this->studentService->updateProfilePicture(
            $student,
            $request->file('profile_picture')
        );

        return new StudentResource($student);
    }

    /**
     * Delete a student's profile picture.
     */
    public function destroy(Student $student): StudentResource
    {
        Gate::authorize('update', $student);

        $student = $this->studentService->deleteProfilePicture($student);

        return new StudentResource($student);
    }
}
