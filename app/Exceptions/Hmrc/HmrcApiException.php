<?php

declare(strict_types=1);

namespace App\Exceptions\Hmrc;

use App\Enums\HmrcErrorCode;
use RuntimeException;
use Throwable;

class HmrcApiException extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $errors  HMRC's multi-error envelope.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $hmrcCode = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ?HmrcErrorCode
    {
        return HmrcErrorCode::tryFromString($this->hmrcCode);
    }

    public function userMessage(): string
    {
        return $this->errorCode()?->userMessage() ?? HmrcErrorCode::default();
    }
}
