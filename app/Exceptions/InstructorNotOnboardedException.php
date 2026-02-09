<?php

namespace App\Exceptions;

use Exception;

class InstructorNotOnboardedException extends Exception
{
    public function __construct(string $message = 'Instructor has not completed Stripe Connect onboarding')
    {
        parent::__construct($message);
    }
}
