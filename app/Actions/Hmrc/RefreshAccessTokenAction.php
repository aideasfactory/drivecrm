<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Enums\HmrcTokenRefreshOutcome;
use App\Exceptions\Hmrc\HmrcReconnectRequiredException;
use App\Models\HmrcToken;
use App\Models\HmrcTokenRefreshLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshAccessTokenAction
{
    /**
     * Atomically refresh the user's access token.
     *
     * HMRC invalidates the old refresh token the moment a new one is issued,
     * so this operation MUST be serialised per user — losing the new refresh
     * token strands the user. Refresh attempts are always logged.
     */
    public function __invoke(HmrcToken $token): HmrcToken
    {
        return DB::transaction(function () use ($token): HmrcToken {
            $locked = HmrcToken::query()
                ->whereKey($token->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isRefreshTokenExpired()) {
                $this->log($locked, HmrcTokenRefreshOutcome::FailureInvalidGrant, 'refresh_token_expired');

                throw new HmrcReconnectRequiredException('Your HMRC refresh token has expired. Please reconnect.');
            }

            $environment = (string) config('hmrc.environment', 'sandbox');
            $tokenUrl = (string) config("hmrc.urls.{$environment}.token");

            try {
                $response = Http::asForm()->acceptJson()->post($tokenUrl, [
                    'grant_type' => 'refresh_token',
                    'client_id' => (string) config('hmrc.client_id'),
                    'client_secret' => (string) config('hmrc.client_secret'),
                    'refresh_token' => $locked->refresh_token,
                ]);
            } catch (ConnectionException $exception) {
                $this->log($locked, HmrcTokenRefreshOutcome::FailureNetwork, $exception->getMessage());

                throw $exception;
            } catch (Throwable $exception) {
                $this->log($locked, HmrcTokenRefreshOutcome::FailureOther, $exception->getMessage());

                throw $exception;
            }

            if ($response->failed()) {
                $errorCode = $response->json('error') ?? 'unknown_error';

                $outcome = $errorCode === 'invalid_grant'
                    ? HmrcTokenRefreshOutcome::FailureInvalidGrant
                    : HmrcTokenRefreshOutcome::FailureOther;

                $this->log($locked, $outcome, (string) $errorCode);

                Log::warning('HMRC token refresh failed', [
                    'user_id' => $locked->user_id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                if ($outcome === HmrcTokenRefreshOutcome::FailureInvalidGrant) {
                    throw new HmrcReconnectRequiredException('HMRC rejected the refresh token. Please reconnect.');
                }

                throw new \RuntimeException('HMRC token refresh failed.');
            }

            $payload = $response->json();
            $now = now();

            $locked->forceFill([
                'access_token' => $payload['access_token'],
                'refresh_token' => $payload['refresh_token'] ?? $locked->refresh_token,
                'token_type' => $payload['token_type'] ?? $locked->token_type,
                'expires_at' => $now->copy()->addSeconds((int) ($payload['expires_in'] ?? 14400)),
                'refresh_expires_at' => $now->copy()->addSeconds((int) ($payload['refresh_token_expires_in'] ?? (60 * 60 * 24 * 30 * 18))),
                'last_refreshed_at' => $now,
                'last_expiry_warning_at' => null,
            ])->save();

            $this->log($locked, HmrcTokenRefreshOutcome::Success);

            return $locked;
        });
    }

    private function log(HmrcToken $token, HmrcTokenRefreshOutcome $outcome, ?string $errorCode = null): void
    {
        HmrcTokenRefreshLog::query()->create([
            'user_id' => $token->user_id,
            'outcome' => $outcome,
            'error_code' => $errorCode,
            'attempted_at' => now(),
        ]);
    }
}
