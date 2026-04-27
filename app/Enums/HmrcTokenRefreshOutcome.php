<?php

declare(strict_types=1);

namespace App\Enums;

enum HmrcTokenRefreshOutcome: string
{
    case Success = 'success';
    case FailureInvalidGrant = 'failure_invalid_grant';
    case FailureNetwork = 'failure_network';
    case FailureOther = 'failure_other';

    public function isFailure(): bool
    {
        return $this !== self::Success;
    }
}
