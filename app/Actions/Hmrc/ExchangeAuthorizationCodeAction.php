<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcOAuthState;
use App\Models\HmrcToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeAuthorizationCodeAction
{
    public function __invoke(User $user, string $code, string $state): HmrcToken
    {
        $stateRow = HmrcOAuthState::query()
            ->where('user_id', $user->id)
            ->where('state', $state)
            ->notExpired()
            ->first();

        if (! $stateRow) {
            throw new HmrcApiException(
                'OAuth state is invalid or has expired.',
                statusCode: 400,
                hmrcCode: 'INVALID_REQUEST',
            );
        }

        $environment = (string) config('hmrc.environment', 'sandbox');
        $tokenUrl = (string) config("hmrc.urls.{$environment}.token");

        $response = Http::asForm()->acceptJson()->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => (string) config('hmrc.client_id'),
            'client_secret' => (string) config('hmrc.client_secret'),
            'redirect_uri' => $stateRow->redirect_uri,
            'code' => $code,
            'code_verifier' => $stateRow->code_verifier,
        ]);

        if ($response->failed()) {
            Log::warning('HMRC token exchange failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HmrcApiException(
                'HMRC token exchange failed.',
                statusCode: $response->status(),
                hmrcCode: $response->json('error') ?? 'INVALID_REQUEST',
            );
        }

        $payload = $response->json();
        $now = now();

        $token = HmrcToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $payload['access_token'],
                'refresh_token' => $payload['refresh_token'],
                'token_type' => $payload['token_type'] ?? 'bearer',
                'scopes' => $stateRow->scopes,
                'expires_at' => $now->copy()->addSeconds((int) ($payload['expires_in'] ?? 14400)),
                'refresh_expires_at' => $now->copy()->addSeconds((int) ($payload['refresh_token_expires_in'] ?? (60 * 60 * 24 * 30 * 18))),
                'last_refreshed_at' => null,
                'last_expiry_warning_at' => null,
                'connected_at' => $now,
            ],
        );

        $stateRow->delete();

        return $token;
    }
}
