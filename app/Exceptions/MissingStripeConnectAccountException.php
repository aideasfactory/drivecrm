<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class MissingStripeConnectAccountException extends RuntimeException
{
    /**
     * Shown when Stripe reports the stored Connect account does not exist
     * on the platform these API keys belong to. The raw account id is omitted
     * so instructors are told to contact support instead of seeing a Stripe error.
     * An owner can then reset the saved connection; the next connect creates a new account.
     */
    public const MESSAGE = 'We couldn\'t find this Stripe account. Please contact support so they can reset your Stripe connection. After that, connecting again creates a new account.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
