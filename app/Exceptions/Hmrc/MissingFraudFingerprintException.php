<?php

declare(strict_types=1);

namespace App\Exceptions\Hmrc;

use RuntimeException;
use Throwable;

class MissingFraudFingerprintException extends RuntimeException
{
    public function __construct(
        string $message = 'A fresh device fingerprint is required before this HMRC action.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
