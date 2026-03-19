<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateInstructorProfileRequest;
use App\Http\Resources\V1\InstructorProfileResource;
use App\Services\InstructorService;
use Illuminate\Support\Facades\Gate;

class InstructorProfileController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Update the authenticated instructor's profile.
     */
    public function update(UpdateInstructorProfileRequest $request): InstructorProfileResource
    {
        $instructor = $request->user()->instructor;

        Gate::authorize('update', $instructor);

        $instructor = $this->instructorService->updateProfile($instructor, $request->validated());

        return new InstructorProfileResource($instructor);
    }
}
