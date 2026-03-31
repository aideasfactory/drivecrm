<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePickupPointRequest;
use App\Http\Resources\V1\StudentPickupPointResource;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
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

    /**
     * Store a new pickup point for a student.
     */
    public function store(StorePickupPointRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $pickupPoint = $this->studentService->storePickupPoint($student, $request->validated());

        return (new StudentPickupPointResource($pickupPoint))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a pickup point for a student.
     */
    public function destroy(Request $request, Student $student, StudentPickupPoint $pickupPoint): JsonResponse
    {
        Gate::authorize('update', $student);

        if ($pickupPoint->student_id !== $student->id) {
            return response()->json(['message' => 'Pickup point not found for this student.'], 404);
        }

        $this->studentService->deletePickupPoint($pickupPoint);

        return response()->json(['message' => 'Pickup point deleted successfully.']);
    }

    /**
     * Set a pickup point as the default (primary) for a student.
     */
    public function setDefault(Request $request, Student $student, StudentPickupPoint $pickupPoint): StudentPickupPointResource|JsonResponse
    {
        Gate::authorize('update', $student);

        if ($pickupPoint->student_id !== $student->id) {
            return response()->json(['message' => 'Pickup point not found for this student.'], 404);
        }

        $pickupPoint = $this->studentService->setDefaultPickupPoint($pickupPoint);

        return new StudentPickupPointResource($pickupPoint);
    }
}
