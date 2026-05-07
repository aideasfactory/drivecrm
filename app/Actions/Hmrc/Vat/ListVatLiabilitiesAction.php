<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Vat;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;

class ListVatLiabilitiesAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Fetch VAT liabilities (outstanding charges) for a date range. HMRC requires
     * both `from` and `to` to be present.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function __invoke(User $user, string $from, string $to, array $fraudContext = []): array
    {
        $vrn = $this->resolveVrn($user);

        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/organisations/vat/{$vrn}/liabilities",
            version: '1.0',
            query: ['from' => $from, 'to' => $to],
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );
    }

    private function resolveVrn(User $user): string
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->vrn) || $instructor->vrn === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a VAT registration number (VRN) before fetching VAT liabilities.',
                statusCode: 400,
            );
        }

        return $instructor->vrn;
    }
}
