<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class SlotNoLongerAvailableException extends Exception
{
    public function __construct(string $message = 'One or more of the selected lesson times are no longer available')
    {
        parent::__construct($message);
    }
}
