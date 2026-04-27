<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Exceptions\Hmrc\HmrcReconnectRequiredException;
use App\Models\HmrcToken;
use App\Models\User;

class GetValidAccessTokenAction
{
    public function __construct(
        private readonly RefreshAccessTokenAction $refresh,
    ) {}

    public function __invoke(User $user): string
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();

        if (! $token) {
            throw new HmrcReconnectRequiredException('No HMRC connection on file. Please connect first.');
        }

        $buffer = (int) config('hmrc.access_token_refresh_buffer', 60);

        if ($token->isAccessTokenExpired($buffer)) {
            $token = ($this->refresh)($token);
        }

        return $token->access_token;
    }
}
