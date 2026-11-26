<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * The learner tried to accept a Learner Terms version that is no longer the
 * current one (a newer version was published while they were reading), or
 * there is no published version at all.
 */
class LearnerTermsVersionOutdatedException extends Exception
{
    public function __construct(
        public readonly ?string $currentVersion,
        string $message = 'Our Learner Terms have been updated. Please read the latest version and accept again.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
