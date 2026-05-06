<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;

class RetrieveAccountBalanceAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function __invoke(User $user, array $fraudContext = []): array
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino) || $instructor->nino === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before retrieving the SA account balance.',
                statusCode: 400,
            );
        }

        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/accounts/self-assessment/{$instructor->nino}/balance-and-transactions",
            version: '4.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );
    }
}
