<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Hmrc\BuildAuthorizationUrlAction;
use App\Actions\Hmrc\CallHmrcApiAction;
use App\Actions\Hmrc\ExchangeAuthorizationCodeAction;
use App\Actions\Hmrc\GetValidAccessTokenAction;
use App\Actions\Hmrc\HelloWorldAction;
use App\Actions\Hmrc\RefreshAccessTokenAction;
use App\Models\HmrcToken;
use App\Models\User;

class HmrcService extends BaseService
{
    public function __construct(
        protected BuildAuthorizationUrlAction $buildAuthorizationUrl,
        protected ExchangeAuthorizationCodeAction $exchangeAuthorizationCode,
        protected RefreshAccessTokenAction $refreshAccessToken,
        protected GetValidAccessTokenAction $getValidAccessToken,
        protected CallHmrcApiAction $callHmrcApi,
        protected HelloWorldAction $helloWorld,
    ) {}

    /**
     * @return array{
     *     connected: bool,
     *     connected_at: ?string,
     *     expires_at: ?string,
     *     refresh_expires_at: ?string,
     *     scopes: array<int, string>,
     *     days_until_refresh_expiry: ?int,
     * }
     */
    public function connectionStatusFor(User $user): array
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();

        if (! $token) {
            return [
                'connected' => false,
                'connected_at' => null,
                'expires_at' => null,
                'refresh_expires_at' => null,
                'scopes' => [],
                'days_until_refresh_expiry' => null,
            ];
        }

        return [
            'connected' => true,
            'connected_at' => $token->connected_at->toIso8601String(),
            'expires_at' => $token->expires_at->toIso8601String(),
            'refresh_expires_at' => $token->refresh_expires_at->toIso8601String(),
            'scopes' => $token->scopes ?? [],
            'days_until_refresh_expiry' => $token->daysUntilRefreshExpiry(),
        ];
    }

    public function beginAuthorization(User $user): string
    {
        $scopes = (array) config('hmrc.scopes.hello_world', ['hello']);

        return ($this->buildAuthorizationUrl)($user, $scopes);
    }

    public function completeAuthorization(User $user, string $code, string $state): HmrcToken
    {
        return ($this->exchangeAuthorizationCode)($user, $code, $state);
    }

    public function disconnect(User $user): void
    {
        HmrcToken::query()->where('user_id', $user->id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function helloWorld(User $user): array
    {
        return ($this->helloWorld)($user);
    }
}
