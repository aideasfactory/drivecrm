<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitHazardPerceptionAttemptRequest;
use App\Http\Resources\V1\HazardPerceptionAttemptResource;
use App\Http\Resources\V1\HazardPerceptionSummaryResource;
use App\Http\Resources\V1\HazardPerceptionVideoResource;
use App\Models\HazardPerceptionVideo;
use App\Services\HazardPerceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
