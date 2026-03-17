<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentResource;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorStudentController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Get the authenticated instructor's students grouped by status.
     */
    public function index(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        $grouped = $this->instructorService->getGroupedStudents($instructor);

        return response()->json([
            'data' => [
                'active' => StudentResource::collection($grouped['active']),
                'passed' => StudentResource::collection($grouped['passed']),
                'inactive' => StudentResource::collection($grouped['inactive']),
                'recent_activity' => StudentResource::collection($grouped['recent_activity']),
            ],
        ]);
    }
}
