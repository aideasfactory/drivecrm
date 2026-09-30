<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePushTokenRequest;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;

class PushNotificationController extends Controller
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function storeToken(StorePushTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->pushNotificationService->storeToken(
            $user,
            $request->validated('expo_push_token'),
        );

        return response()->json([
            'message' => 'Push token stored successfully.',
        ]);
    }

    /**
     * Remove the device's push token. Always 200 (idempotent) — a token that
     * doesn't match the stored one is left untouched.
     */
    public function destroyToken(StorePushTokenRequest $request): JsonResponse
    {
        $this->pushNotificationService->removeToken(
            $request->user(),
            $request->validated('expo_push_token'),
        );

        return response()->json([
            'message' => 'Push token removed successfully.',
        ]);
    }
}
