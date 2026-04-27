<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CallHmrcApiAction
{
    public function __construct(
        private readonly GetValidAccessTokenAction $getValidAccessToken,
    ) {}

    /**
     * Call an HMRC endpoint with the user's access token.
     *
     * @param  string  $method  HTTP verb (GET/POST/PUT/DELETE).
     * @param  string  $path    Path relative to the API base (e.g. `/hello/user`).
     * @param  string  $version HMRC API version pinned via the Accept header (e.g. `1.0`).
     * @param  array<string, mixed>  $payload  Body for POST/PUT requests.
     * @param  array<string, string>  $extraHeaders  Optional additional headers (e.g. fraud-prevention headers).
     * @param  array<string, mixed>  $query  Optional query parameters.
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
    ): array {
        $token = ($this->getValidAccessToken)($user);

        $environment = (string) config('hmrc.environment', 'sandbox');
        $base = (string) config("hmrc.urls.{$environment}.api");
        $url = $base.'/'.ltrim($path, '/');

        $request = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(array_merge([
                'Accept' => "application/vnd.hmrc.{$version}+json",
            ], $extraHeaders));

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

        return is_array($body = $response->json()) ? $body : [];
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
}
