<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Vat;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;

class RetrieveVatReturnAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Read back a previously-submitted VAT return from HMRC.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function __invoke(User $user, string $periodKey, array $fraudContext = []): array
    {
        $vrn = $this->resolveVrn($user);
        $encoded = rawurlencode($periodKey);

        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/organisations/vat/{$vrn}/returns/{$encoded}",
            version: '1.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );
    }

    private function resolveVrn(User $user): string
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->vrn) || $instructor->vrn === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a VAT registration number (VRN) before retrieving a VAT return.',
                statusCode: 400,
            );
        }

        return $instructor->vrn;
    }
}
