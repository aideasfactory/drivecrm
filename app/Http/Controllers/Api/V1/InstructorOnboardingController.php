<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteOnboardingStepRequest;
use App\Http\Resources\V1\AppOnboardingResource;
use App\Services\InstructorService;
use Illuminate\Http\Request;

class InstructorOnboardingController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Get the authenticated instructor's app onboarding progress.
     */
    public function show(Request $request): AppOnboardingResource
    {
        return new AppOnboardingResource($request->user()->instructor);
    }

    /**
     * Mark an app onboarding step as complete for the authenticated instructor.
     */
    public function completeStep(CompleteOnboardingStepRequest $request): AppOnboardingResource
    {
        $instructor = $this->instructorService->completeAppOnboardingStep(
            $request->user()->instructor,
            $request->integer('step')
        );

        return new AppOnboardingResource($instructor);
    }
}
