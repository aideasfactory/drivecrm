<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa\FinalDeclaration;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Enums\ItsaSupplementaryType;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\User;

class RetrieveSupplementaryAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function __invoke(
        User $user,
        ItsaSupplementaryType $type,
        string $taxYear,
        array $fraudContext = [],
    ): array {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino) || $instructor->nino === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before retrieving supplementary data.',
                statusCode: 400,
            );
        }

        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: $type->hmrcPath((string) $instructor->nino, $taxYear),
            version: $type->hmrcVersion(),
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );
    }
}
