<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc;

use App\Actions\Hmrc\EnsureHmrcDeviceIdentifierAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHmrcFingerprintRequest;
use App\Services\HmrcService;
use App\Support\Hmrc\FraudRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HmrcFraudHeadersController extends Controller
{
    public function __construct(
        protected HmrcService $hmrc,
        protected EnsureHmrcDeviceIdentifierAction $ensureDeviceIdentifier,
    ) {}

    /**
     * Capture/refresh the user's device fingerprint. Called via axios from the
     * `useHmrcAction` composable immediately before any interactive HMRC call
     * that requires fraud-prevention headers.
     */
    public function storeFingerprint(StoreHmrcFingerprintRequest $request): JsonResponse
    {
        ($this->ensureDeviceIdentifier)($request, $request->user());

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

        try {
            $result = $this->hmrc->validateFraudHeaders($user, FraudRequestContext::fromRequest($request));
        } catch (MissingFraudFingerprintException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (HmrcApiException $exception) {
            $status = $exception->statusCode >= 400 && $exception->statusCode <= 599
                ? $exception->statusCode
                : 502;

            return response()->json([
                'message' => $exception->getMessage(),
                'status' => $exception->statusCode,
            ], $status);
        }

        return response()->json($result);
    }
}
