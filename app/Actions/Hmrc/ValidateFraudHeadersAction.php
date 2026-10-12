<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Models\HmrcClientFingerprint;
use App\Models\HmrcToken;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ValidateFraudHeadersAction
{
    public function __construct(
        private readonly BuildFraudPreventionHeadersAction $buildFraudHeaders,
        private readonly GetApplicationAccessTokenAction $applicationAccessToken,
    ) {}

    /**
     * Send the current WEB_APP_VIA_SERVER header set to HMRC's sandbox
     * Test Fraud Prevention Headers API.
     *
     * GET /test/fraud-prevention-headers/validate
     * Auth: application-restricted client-credentials token.
     *
     * @param  array{
     *     ip?: ?string,
     *     port?: ?string,
     *     ip_collected_at?: ?\Illuminate\Support\Carbon,
     *     has_mfa?: bool,
     *     mfa?: ?array{type?: string, timestamp?: string, unique_reference?: string},
     * }  $context
     * @return array{
     *     code: ?string,
     *     message: ?string,
     *     spec_version: ?string,
     *     headers_sent: array<string, string>,
     *     errors: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     raw: array<string, mixed>,
     * }
     */
    public function __invoke(User $user, array $context = []): array
    {
        if ((string) config('hmrc.environment', 'sandbox') !== 'sandbox') {
            throw new HmrcApiException(
                message: 'The Test Fraud Prevention Headers API is only called when HMRC_ENVIRONMENT is sandbox.',
                statusCode: 400,
            );
        }

        $token = HmrcToken::query()->where('user_id', $user->id)->first();

        if ($token === null) {
            throw new HmrcApiException(
                message: 'You must connect to HMRC before validating fraud headers.',
                statusCode: 400,
            );
        }

        $fingerprint = HmrcClientFingerprint::query()
            ->where('hmrc_token_id', $token->id)
            ->first();

        if ($fingerprint === null) {
            throw new MissingFraudFingerprintException;
        }

        $maxAge = (int) config('hmrc.fraud_headers.fingerprint_max_age_minutes', 30);

        if ($fingerprint->isStale($maxAge)) {
            throw new MissingFraudFingerprintException(
                'Your device fingerprint is older than '.$maxAge.' minutes. Refresh the page and try again.',
            );
        }

        $headers = ($this->buildFraudHeaders)($user, $fingerprint, $context);
        $accessToken = ($this->applicationAccessToken)();
        $url = rtrim((string) config('hmrc.urls.sandbox.api'), '/').'/test/fraud-prevention-headers/validate';

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/vnd.hmrc.1.0+json',
                    ...$headers,
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new HmrcApiException(
                message: 'Could not reach the HMRC Test Fraud Prevention Headers API.',
                statusCode: 502,
                previous: $exception,
            );
        }

        if ($response->failed()) {
            $hmrcMessage = $response->json('message');
            $message = is_string($hmrcMessage) && $hmrcMessage !== ''
                ? $hmrcMessage
                : 'HMRC fraud-header validation failed.';

            if ($response->status() === 403) {
                $message .= ' Subscribe the sandbox application to the Test Fraud Prevention Headers API.';
            }

            throw new HmrcApiException(
                message: $message,
                statusCode: $response->status(),
                hmrcCode: is_string($response->json('code')) ? $response->json('code') : null,
            );
        }

        $body = $response->json();
        $raw = is_array($body) ? $body : [];
        $errors = is_array($raw['errors'] ?? null) ? array_values($raw['errors']) : [];
        $warnings = is_array($raw['warnings'] ?? null) ? array_values($raw['warnings']) : [];

        return [
            'code' => is_string($raw['code'] ?? null) ? $raw['code'] : null,
            'message' => is_string($raw['message'] ?? null) ? $raw['message'] : null,
            'spec_version' => is_string($raw['specVersion'] ?? null) ? $raw['specVersion'] : null,
            'headers_sent' => $headers,
            'errors' => $errors,
            'warnings' => $warnings,
            'raw' => $raw,
        ];
    }
}
