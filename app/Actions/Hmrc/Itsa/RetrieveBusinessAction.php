<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;

class RetrieveBusinessAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function __invoke(User $user, string $businessId, array $fraudContext = []): array
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino)) {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before fetching HMRC business details.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/individuals/business/details/{$nino}/{$businessId}",
            version: '2.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );
    }
}
