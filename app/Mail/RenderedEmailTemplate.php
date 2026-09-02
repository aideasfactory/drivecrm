<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class RenderedEmailTemplate
{
    public function __construct(
        public string $subject,
        public string $greeting,
        public string $body,
        public string $salutation,
        public ?string $actionText,
        public ?string $actionUrl,
    ) {}
}
