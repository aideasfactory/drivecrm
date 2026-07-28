<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccountDeletionRequest;
use App\Http\Resources\V1\AccountDeletionRequestResource;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountDeletionController extends Controller
{
    public function __construct(
        protected AccountDeletionService $accountDeletionService
    ) {}

    /**
     * Create a pending deletion request (30-day grace period) for the
     * authenticated user.
     */
    public function store(StoreAccountDeletionRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($this->accountDeletionService->getPendingRequest($user)) {
            throw ValidationException::withMessages([
                'account' => 'A deletion request is already pending for this account.',
            ]);
        }

        $deletionRequest = $this->accountDeletionService->requestDeletion(
            $user,
            $request->validated('reason'),
        );

        return (new AccountDeletionRequestResource($deletionRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Return the authenticated user's latest deletion request, or null if
     * they have never made one.
     */
    public function show(Request $request): AccountDeletionRequestResource|JsonResponse
    {
        $deletionRequest = $this->accountDeletionService->getLatestRequest($request->user());

        if (! $deletionRequest) {
            return response()->json(['data' => null]);
        }

        return new AccountDeletionRequestResource($deletionRequest);
    }

    /**
     * Cancel the authenticated user's pending deletion request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $deletionRequest = $this->accountDeletionService->getPendingRequest($request->user());

        if (! $deletionRequest) {
            return response()->json(['message' => 'No pending deletion request found.'], 404);
        }

        $this->accountDeletionService->cancelRequest($deletionRequest);

        return response()->json(['message' => 'Deletion request cancelled successfully.']);
    }
}
