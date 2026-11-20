<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LessonDeletionRequest;
use App\Services\LessonDeletionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Instructors' requests to delete past lessons logged by mistake, for owners
 * (full access and restricted / bookings team) to approve or reject.
 */
class LessonDeletionRequestController extends Controller
{
    public function __construct(
        protected LessonDeletionRequestService $lessonDeletionRequestService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('LessonDeletionRequests/Index', [
            'pending' => $this->lessonDeletionRequestService->getAdminRows(true),
            'history' => Inertia::defer(fn (): array => $this->lessonDeletionRequestService->getAdminRows(false)),
        ]);
    }

    /**
     * Approve the request and permanently delete the lesson.
     */
    public function approve(Request $request, LessonDeletionRequest $lessonDeletionRequest): JsonResponse
    {
        try {
            $this->lessonDeletionRequestService->approve($lessonDeletionRequest, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Request approved. The lesson has been permanently deleted and the instructor notified.']);
    }

    /**
     * Reject the request and keep the lesson.
     */
    public function reject(Request $request, LessonDeletionRequest $lessonDeletionRequest): JsonResponse
    {
        try {
            $this->lessonDeletionRequestService->reject($lessonDeletionRequest, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Request rejected. The lesson has been kept and the instructor notified.']);
    }
}
