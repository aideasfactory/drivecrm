<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Models\HmrcClientFingerprint;
use App\Models\HmrcToken;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CallHmrcApiAction
{
    public function __construct(
        private readonly GetValidAccessTokenAction $getValidAccessToken,
        private readonly BuildFraudPreventionHeadersAction $buildFraudHeaders,
    ) {}

    /**
     * Call an HMRC endpoint with the user's access token.
     *
     * @param  string  $method  HTTP verb (GET/POST/PUT/DELETE).
     * @param  string  $path  Path relative to the API base (e.g. `/hello/user`).
     * @param  string  $version  HMRC API version pinned via the Accept header (e.g. `1.0`).
     * @param  array<string, mixed>  $payload  Body for POST/PUT requests.
     * @param  array<string, string>  $extraHeaders  Optional additional headers (caller-supplied; merged on top of fraud headers if both are present).
     * @param  array<string, mixed>  $query  Optional query parameters.
     * @param  bool  $withFraudHeaders  When true, build the WEB_APP_VIA_SERVER fraud-prevention header set from the user's stored fingerprint and merge it.
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext  Per-request context for fraud headers (IP, port, MFA marker).
     * @return array<string, mixed>
     */
    public function __invoke(
        User $user,
        string $method,
        string $path,
        string $version,
        array $payload = [],
        array $extraHeaders = [],
        array $query = [],
        bool $withFraudHeaders = false,
        array $fraudContext = [],
    ): array {
        $token = ($this->getValidAccessToken)($user);

        $environment = (string) config('hmrc.environment', 'sandbox');
        $base = (string) config("hmrc.urls.{$environment}.api");
        $url = $base.'/'.ltrim($path, '/');

        $headers = ['Accept' => "application/vnd.hmrc.{$version}+json"];

        if ($withFraudHeaders) {
            $headers = array_merge($headers, $this->resolveFraudHeaders($user, $fraudContext));
        }

        $headers = array_merge($headers, $extraHeaders);

        $request = Http::withToken($token)
            ->withHeaders($headers);

        $verb = strtoupper($method);

        $response = match ($verb) {
            'GET' => $request->get($url, $query),
            'DELETE' => $request->delete($url, $query),
            'POST' => $request->post($url, $payload),
            'PUT' => $request->put($url, $payload),
            'PATCH' => $request->patch($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $this->logResponse($user, $verb, $path, $response);

        if ($response->failed()) {
            throw $this->toException($response);
        }

        $body = is_array($body = $response->json()) ? $body : [];

        $correlationId = $response->header('X-CorrelationId');
        if (is_string($correlationId) && $correlationId !== '') {
            $body['_correlationId'] = $correlationId;
        }

        return $body;
    }

    private function toException(Response $response): HmrcApiException
    {
        $body = $response->json();
        $code = is_array($body) ? ($body['code'] ?? null) : null;
        $message = is_array($body) ? ($body['message'] ?? 'HMRC API error') : 'HMRC API error';
        $errors = is_array($body) && isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [];

        return new HmrcApiException(
            message: (string) $message,
            statusCode: $response->status(),
            hmrcCode: is_string($code) ? $code : null,
            errors: $errors,
        );
    }

    private function logResponse(User $user, string $verb, string $path, Response $response): void
    {
        Log::info('HMRC API call', [
            'user_id' => $user->id,
            'method' => $verb,
            'path' => $path,
            'status' => $response->status(),
        ]);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $context
     * @return array<string, string>
     */
    private function resolveFraudHeaders(User $user, array $context): array
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();
        if ($token === null) {
            throw new MissingFraudFingerprintException(
                'No HMRC token on file — connect to HMRC before calling protected endpoints.',
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
                'Your device fingerprint is older than '.$maxAge.' minutes. Refresh the page and retry.',
            );
        }

        return ($this->buildFraudHeaders)($user, $fingerprint, $context);
    }
}
