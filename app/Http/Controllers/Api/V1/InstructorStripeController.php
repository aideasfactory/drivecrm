<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StripeAccountStatusResource;
use App\Http\Resources\V1\StripeOnboardingLinkResource;
use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class InstructorStripeController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Mint a fresh Stripe Connect onboarding link for the authenticated
     * instructor, creating their Connect account first if they don't have one.
     * Idempotent — safe to call again to resume an unfinished onboarding.
     */
    public function startOnboarding(Request $request): StripeOnboardingLinkResource|JsonResponse
    {
        $instructor = $request->user()->instructor;

        try {
            $link = $this->instructorService->startStripeOnboarding(
                $instructor,
                $this->mobileReturnUrl($instructor),
                $this->mobileRefreshUrl($instructor),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return new StripeOnboardingLinkResource($link);
    }

    /**
     * Live-sync and return the authenticated instructor's Stripe Connect
     * status. Call after the in-app browser closes to verify completion.
     */
    public function status(Request $request): StripeAccountStatusResource|JsonResponse
    {
        try {
            $instructor = $this->instructorService->syncStripeAccountStatus(
                $request->user()->instructor
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return new StripeAccountStatusResource($instructor);
    }

    protected function mobileReturnUrl(Instructor $instructor): string
    {
        return URL::signedRoute('stripe.mobile.return', ['instructor' => $instructor->id]);
    }

    protected function mobileRefreshUrl(Instructor $instructor): string
    {
        return URL::signedRoute('stripe.mobile.refresh', ['instructor' => $instructor->id]);
    }
}
