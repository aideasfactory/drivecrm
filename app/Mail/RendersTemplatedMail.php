<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Services\EmailTemplateService;
use Illuminate\Notifications\Messages\MailMessage;

trait RendersTemplatedMail
{
    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function templatedMail(EmailTemplateKey $key, array $replacements = [], ?string $actionUrl = null): MailMessage
    {
        return app(EmailTemplateService::class)->toMailMessage($key, $replacements, $actionUrl);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function renderedTemplate(EmailTemplateKey $key, array $replacements = [], ?string $actionUrl = null): RenderedEmailTemplate
    {
        return app(EmailTemplateService::class)->render($key, $replacements, $actionUrl);
    }

    /**
     * @return array<string, mixed>
     */
    protected function templatedViewData(RenderedEmailTemplate $rendered): array
    {
        return app(EmailTemplateService::class)->viewData($rendered);
    }
}
