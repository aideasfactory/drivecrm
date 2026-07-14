<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RevisionQuestionsRequest;
use App\Http\Requests\Api\V1\StartMockTestRequest;
use App\Http\Requests\Api\V1\SubmitMockTestRequest;
use App\Http\Requests\Api\V1\TestHistoryRequest;
use App\Http\Resources\V1\MockTestQuestionResource;
use App\Http\Resources\V1\MockTestResource;
use App\Http\Resources\V1\MockTestReviewResource;
use App\Http\Resources\V1\MockTestRevisionQuestionResource;
use App\Http\Resources\V1\MockTestSummaryResource;
use App\Models\MockTest;
use App\Models\MockTestQuestion;
use App\Services\MockTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MockTestController extends Controller
{
    public function __construct(
        protected MockTestService $mockTestService,
    ) {}

    /**
     * Paginated history of the student's completed tests, newest first.
     * Optional filters: category (incl. Mixed), mode (mock|practice).
     */
    public function index(TestHistoryRequest $request): AnonymousResourceCollection
    {
        $student = $request->user()->student;

        $history = $this->mockTestService->getTestHistory(
            $student,
            $request->validated('category'),
            $request->validated('mode'),
            (int) ($request->validated('per_page') ?? 20),
        );

        return MockTestResource::collection($history);
    }

    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        $category = $request->query('category');

        $summary = $this->mockTestService->getSummary($student, $category);

        return response()->json([
            'data' => new MockTestSummaryResource($summary),
        ]);
    }

    /**
     * Question-bank index for the revision UI: every category with its
     * question count and topics (each with a per-topic count).
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => $this->mockTestService->getQuestionBankIndex(),
            ],
        ]);
    }

    /**
     * Paginated revision questions for a category (optionally a topic).
     * Includes correct answers + explanations — revision mode only.
     */
    public function questions(RevisionQuestionsRequest $request): AnonymousResourceCollection
    {
        $questions = $this->mockTestService->getRevisionQuestions(
            $request->validated('category'),
            $request->validated('topic'),
            (int) ($request->validated('per_page') ?? 20),
        );

        return MockTestRevisionQuestionResource::collection($questions);
    }

    /**
     * Single question with full detail (revision resource — includes the
     * correct answer + explanation). Used when the app drills into one
     * question from a results or performance screen.
     */
    public function question(MockTestQuestion $mockTestQuestion): JsonResponse
    {
        return response()->json([
            'data' => new MockTestRevisionQuestionResource($mockTestQuestion),
        ]);
    }

    public function start(StartMockTestRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        $result = $this->mockTestService->startTest(
            $student,
            $request->validated('category'),
            $request->validated('topic'),
            $request->validated('mode') ?? 'mock',
            $request->validated('question_count') !== null ? (int) $request->validated('question_count') : null,
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
