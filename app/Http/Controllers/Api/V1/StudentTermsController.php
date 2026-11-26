<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LearnerTermsVersionOutdatedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptLearnerTermsRequest;
use App\Http\Resources\V1\LearnerTermsResource;
use App\Services\LearnerTermsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTermsController extends Controller
{
    public function __construct(
        protected LearnerTermsService $learnerTermsService,
    ) {}

    /**
     * Current Learner Terms text and the student's acceptance.
     *
     * Auth: Bearer token — student only.
     */
    public function show(Request $request): LearnerTermsResource
    {
        $student = $request->user()->student;
        abort_if($student === null, 403, 'Only learners have Learner Terms.');

        return new LearnerTermsResource($this->learnerTermsService->statusFor($student));
    }

    /**
     * Record the student's acceptance of the version they were shown.
     *
     * Auth: Bearer token — student only.
     */
    public function accept(AcceptLearnerTermsRequest $request): LearnerTermsResource|JsonResponse
    {
        $student = $request->user()->student;

        try {
            $this->learnerTermsService->accept(
                $student,
                (string) $request->validated('version'),
                (string) $request->validated('platform'),
                $request->validated('app_version'),
            );
        } catch (LearnerTermsVersionOutdatedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'current_version' => $e->currentVersion,
            ], 409);
        }

        return new LearnerTermsResource($this->learnerTermsService->statusFor($student));
    }
}
