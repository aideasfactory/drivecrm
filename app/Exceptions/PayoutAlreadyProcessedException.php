<?php

namespace App\Exceptions;

use Exception;

class PayoutAlreadyProcessedException extends Exception
{
    public function __construct(string $message = 'Payout has already been processed for this lesson')
    {
        parent::__construct($message);
    }
}
