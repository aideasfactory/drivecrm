<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HazardPerceptionTestHistoryRequest;
use App\Http\Requests\Api\V1\StartHazardPerceptionTestRequest;
use App\Http\Requests\Api\V1\SubmitHazardPerceptionAttemptRequest;
use App\Http\Resources\V1\HazardPerceptionAttemptResource;
use App\Http\Resources\V1\HazardPerceptionSummaryResource;
use App\Http\Resources\V1\HazardPerceptionTestResource;
use App\Http\Resources\V1\HazardPerceptionVideoResource;
use App\Models\HazardPerceptionTest;
use App\Models\HazardPerceptionVideo;
use App\Services\HazardPerceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HazardPerceptionController extends Controller
{
    public function __construct(
        protected HazardPerceptionService $hazardPerceptionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');

        $grouped = $this->hazardPerceptionService->getVideos($category);

        $result = $grouped->map(fn ($topics) => $topics->map(
            fn ($videos) => HazardPerceptionVideoResource::collection($videos),
        ));

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Practice mode: score a standalone attempt. The response includes the
     * video (with recap_video_url) so the app can offer the recap.
     */
    public function submit(SubmitHazardPerceptionAttemptRequest $request, HazardPerceptionVideo $hazardPerceptionVideo): JsonResponse
    {
        $student = $request->user()->student;

        $taps = array_map('floatval', $request->validated('taps'));

        $attempt = $this->hazardPerceptionService->recordAttempt(
            $student,
            $hazardPerceptionVideo,
            $taps,
        );

        return response()->json([
            'data' => new HazardPerceptionAttemptResource($attempt),
        ], 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        $category = $request->query('category');

        $summary = $this->hazardPerceptionService->getSummary($student, $category);

        return response()->json([
            'data' => new HazardPerceptionSummaryResource($summary),
        ]);
    }

    /**
     * Paginated history of the student's completed tests, newest first.
     * Optional filters: topic, per_page.
     */
    public function testHistory(HazardPerceptionTestHistoryRequest $request): AnonymousResourceCollection
    {
        $student = $request->user()->student;

        $history = $this->hazardPerceptionService->getTestHistory(
            $student,
            $request->validated('topic'),
            (int) ($request->validated('per_page') ?? 20),
        );

        return HazardPerceptionTestResource::collection($history);
    }

    /**
     * Test mode: create a session with a random video selection
     * (optionally filtered by topic).
     */
    public function startTest(StartHazardPerceptionTestRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        $test = $this->hazardPerceptionService->startTest(
            $student,
            $request->validated('topic'),
        );

        return response()->json([
            'data' => new HazardPerceptionTestResource($test),
        ], 201);
    }

    /**
     * Results view for a completed test, or resume state (next_video)
     * for an in-progress one.
     */
    public function showTest(Request $request, HazardPerceptionTest $hazardPerceptionTest): JsonResponse
    {
        $student = $request->user()->student;

        if ($hazardPerceptionTest->student_id !== $student->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $test = $this->hazardPerceptionService->getTest($hazardPerceptionTest);

        return response()->json([
            'data' => new HazardPerceptionTestResource($test),
        ]);
    }

    /**
     * Score one video within a test session. Submitting the final video
     * completes the test.
     */
    public function submitTestVideo(
        SubmitHazardPerceptionAttemptRequest $request,
        HazardPerceptionTest $hazardPerceptionTest,
        HazardPerceptionVideo $hazardPerceptionVideo,
    ): JsonResponse {
        $student = $request->user()->student;

        if ($hazardPerceptionTest->student_id !== $student->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($hazardPerceptionTest->completed_at !== null) {
            return response()->json(['message' => 'This test has already been completed.'], 422);
        }

        $taps = array_map('floatval', $request->validated('taps'));

        $result = $this->hazardPerceptionService->submitTestVideo(
            $student,
            $hazardPerceptionTest,
            $hazardPerceptionVideo,
            $taps,
        );

        return response()->json([
            'data' => [
                'attempt' => new HazardPerceptionAttemptResource($result['attempt']),
                'test' => new HazardPerceptionTestResource($result['test']),
            ],
        ], 201);
    }
}
