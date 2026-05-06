<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Models\HmrcClientFingerprint;
use App\Models\HmrcToken;
use App\Models\User;

class ValidateFraudHeadersAction
{
    public function __construct(
        private readonly BuildFraudPreventionHeadersAction $buildFraudHeaders,
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Echo the current fraud-prevention header set to HMRC's validator and
     * return its assessment so the UI can surface errors / warnings.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $context
     * @return array{
     *     headers_sent: array<string, string>,
     *     errors: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     raw: array<string, mixed>,
     * }
     */
    public function __invoke(User $user, array $context = []): array
    {
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

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'POST',
            path: '/test/fraud-prevention-headers/validator/validate',
            version: '1.0',
            payload: [],
            extraHeaders: $headers,
        );

        $errors = is_array($response['errors'] ?? null) ? $response['errors'] : [];
        $warnings = is_array($response['warnings'] ?? null) ? $response['warnings'] : [];

        return [
            'headers_sent' => $headers,
            'errors' => $errors,
            'warnings' => $warnings,
            'raw' => $response,
        ];
    }
}
