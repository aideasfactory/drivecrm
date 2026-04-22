<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentProgressResource;
use App\Services\ProgressTrackerService;
use Illuminate\Http\JsonResponse;

class StudentProgressController extends Controller
{
    public function __construct(
        protected ProgressTrackerService $progressTrackerService,
    ) {}

    /**
     * Return the authenticated student's progress across their instructor's
     * framework (GET /api/v1/student/progress).
     */
    public function show(): JsonResponse
    {
        $student = request()->user()->student;

        if ($student === null) {
            abort(404, 'No student profile resolved for this user.');
        }

        $categories = $this->progressTrackerService->getStudentProgress($student);

        return StudentProgressResource::collection($categories)
            ->additional(['score_labels' => config('progress_tracker.score_labels', [])])
            ->response();
    }
}
