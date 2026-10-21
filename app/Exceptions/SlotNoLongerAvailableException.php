<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class SlotNoLongerAvailableException extends Exception
{
    public function __construct(string $message = 'One or more of the selected lesson times are no longer available', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
