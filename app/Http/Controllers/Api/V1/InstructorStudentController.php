<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
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
     *
     * Pass `?active_only=1` for pickers that start a booking: every group then
     * holds only `status = active` students, so `passed` and `inactive` are empty.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $instructor = $request->user()->instructor;

        $grouped = $this->instructorService->getGroupedStudents($instructor);

        if ((bool) ($validated['active_only'] ?? false)) {
            $grouped = [
                'active' => $grouped['active'],
                'passed' => collect(),
                'inactive' => collect(),
                'recent_activity' => $grouped['recent_activity']
                    ->filter(fn (Student $student): bool => $student->isActive())
                    ->values(),
            ];
        }

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
