<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentPickupPointResource;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StudentPickupPointController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Return all pickup points for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function index(Request $request, Student $student): AnonymousResourceCollection
    {
        Gate::authorize('view', $student);

        $pickupPoints = $this->studentService->getPickupPoints($student);

        return StudentPickupPointResource::collection($pickupPoints);
    }
}
