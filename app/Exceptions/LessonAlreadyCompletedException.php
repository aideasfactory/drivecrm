<?php

namespace App\Exceptions;

use Exception;

class LessonAlreadyCompletedException extends Exception
{
    public function __construct(string $message = 'This lesson has already been completed')
    {
        parent::__construct($message);
    }
}
