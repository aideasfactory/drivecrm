<?php

declare(strict_types=1);

namespace App\Exceptions\Hmrc;

use RuntimeException;
use Throwable;

class HmrcReconnectRequiredException extends RuntimeException
{
    public function __construct(string $message = 'HMRC connection needs renewing.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
