<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InstructorPublicProfileResource;
use App\Http\Resources\V1\MyResourceResource;
use App\Http\Resources\V1\ResourceFolderTreeResource;
use App\Services\ResourceApiService;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function __construct(
        protected StudentService $studentService,
        protected ResourceApiService $resourceService
    ) {}

    /**
     * Get the public profile of the student's attached instructor.
     *
     * Auth: Bearer token — student only.
     */
    public function instructor(Request $request): InstructorPublicProfileResource|JsonResponse
    {
        $student = $request->user()->student;

        $instructor = $this->studentService->getInstructorProfile($student);

        if (! $instructor) {
            return response()->json([
                'message' => 'You must be attached to an instructor to view their profile.',
            ], 422);
        }

        return new InstructorPublicProfileResource($instructor);
    }

    /**
     * Get the student's dashboard data (practice hours, suggested resources).
     *
     * Auth: Bearer token — student only.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->student;

        $data = $this->studentService->getDashboard($student);

        $myResources = $this->resourceService->getMyResources($student);

        if ($myResources->isEmpty()) {
            $myResources = $this->resourceService->getRandomPublishedResources();
        }

        $watchedIds = $this->resourceService->getWatchedResourceIds($user);

        ResourceFolderTreeResource::$watchedIds = $watchedIds;

        $data['suggested_resources'] = MyResourceResource::collection($myResources);

        return response()->json(['data' => $data]);
    }
}
