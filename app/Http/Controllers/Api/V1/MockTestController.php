<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartMockTestRequest;
use App\Http\Requests\Api\V1\SubmitMockTestRequest;
use App\Http\Resources\V1\MockTestQuestionResource;
use App\Http\Resources\V1\MockTestResource;
use App\Http\Resources\V1\MockTestReviewResource;
use App\Http\Resources\V1\MockTestSummaryResource;
use App\Models\MockTest;
use App\Services\MockTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockTestController extends Controller
{
    public function __construct(
        protected MockTestService $mockTestService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        $category = $request->query('category');

        $summary = $this->mockTestService->getSummary($student, $category);

        return response()->json([
            'data' => new MockTestSummaryResource($summary),
        ]);
    }

    public function start(StartMockTestRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        $result = $this->mockTestService->startTest(
            $student,
            $request->validated('category'),
            $request->validated('topic'),
        );

        return response()->json([
            'data' => [
                'mock_test' => new MockTestResource($result['mock_test']),
                'questions' => MockTestQuestionResource::collection($result['questions']),
            ],
        ], 201);
    }

    public function submit(SubmitMockTestRequest $request, MockTest $mockTest): JsonResponse
    {
        $student = $request->user()->student;

        if ($mockTest->student_id !== $student->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($mockTest->completed_at !== null) {
            return response()->json(['message' => 'This test has already been submitted.'], 422);
        }

        $result = $this->mockTestService->submitAnswers($mockTest, $request->validated('answers'));

        return response()->json([
            'data' => new MockTestReviewResource($result),
        ]);
    }

    public function review(Request $request, MockTest $mockTest): JsonResponse
    {
        $student = $request->user()->student;

        if ($mockTest->student_id !== $student->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $result = $this->mockTestService->getTestReview($mockTest);

        return response()->json([
            'data' => new MockTestReviewResource($result),
        ]);
    }
}
