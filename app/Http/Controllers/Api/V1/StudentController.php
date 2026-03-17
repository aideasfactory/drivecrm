<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentResource;
use App\Services\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Return a single student record.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function show(Request $request, int $id): StudentResource
    {
        $student = $this->studentService->getById($id);

        $this->authorize('view', $student);

        return new StudentResource($student);
    }
}
