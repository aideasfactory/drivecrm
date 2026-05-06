<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHmrcFingerprintRequest;
use App\Services\HmrcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HmrcFraudHeadersController extends Controller
{
    public function __construct(
        protected HmrcService $hmrc,
    ) {}

    /**
     * Capture/refresh the user's device fingerprint. Called via axios from the
     * `useHmrcAction` composable immediately before any interactive HMRC call
     * that requires fraud-prevention headers.
     */
    public function storeFingerprint(StoreHmrcFingerprintRequest $request): JsonResponse
    {
        try {
            $this->hmrc->storeFingerprint($request->user(), $request->validated());
        } catch (MissingFraudFingerprintException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Fingerprint captured.']);
    }

    /**
     * Echo the fraud-prevention header set to HMRC's validator and return the
     * errors / warnings payload for the UI to render.
     */
    public function validate(Request $request): JsonResponse
    {
        $user = $request->user();

        $context = [
            'ip' => $request->ip(),
            'port' => $request->server('REMOTE_PORT'),
            'has_mfa' => (bool) $request->session()->get('two_factor_authenticated_at'),
        ];

        try {
            $result = $this->hmrc->validateFraudHeaders($user, $context);
        } catch (MissingFraudFingerprintException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (HmrcApiException $exception) {
            return response()->json([
                'message' => $exception->userMessage(),
                'status' => $exception->statusCode,
            ], 502);
        }

        return response()->json($result);
    }
}
