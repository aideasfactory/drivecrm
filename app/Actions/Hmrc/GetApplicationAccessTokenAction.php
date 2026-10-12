<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GetApplicationAccessTokenAction
{
    /**
     * Client-credentials token for application-restricted HMRC APIs.
     *
     * The Test Fraud Prevention Headers API is application-restricted. It does
     * not accept the instructor's user OAuth token.
     */
    public function __invoke(): string
    {
        $clientId = (string) config('hmrc.client_id');
        $clientSecret = (string) config('hmrc.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new HmrcApiException(
                message: 'HMRC client credentials are not configured.',
                statusCode: 500,
            );
        }

        $environment = (string) config('hmrc.environment', 'sandbox');
        $cacheKey = 'hmrc.application_access_token.'.hash('sha256', $environment.'|'.$clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUrl = (string) config("hmrc.urls.{$environment}.token");

        try {
            $response = Http::asForm()->acceptJson()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        } catch (ConnectionException $exception) {
            throw new HmrcApiException(
                message: 'Could not reach HMRC to obtain an application access token.',
                statusCode: 502,
                previous: $exception,
            );
        }

        if ($response->failed()) {
            $description = $response->json('error_description') ?? $response->json('message');
            $message = is_string($description) && $description !== ''
                ? $description
                : 'HMRC rejected the application credentials.';

            throw new HmrcApiException(
                message: $message,
                statusCode: $response->status(),
                hmrcCode: is_string($response->json('error')) ? $response->json('error') : null,
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new HmrcApiException(
                message: 'HMRC did not return an application access token.',
                statusCode: 502,
            );
        }

        $expiresIn = (int) $response->json('expires_in', 14400);
        $buffer = (int) config('hmrc.access_token_refresh_buffer', 60);
        Cache::put($cacheKey, $token, max(60, $expiresIn - $buffer));

        return $token;
    }
}
