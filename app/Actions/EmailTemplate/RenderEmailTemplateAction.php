<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Mail\EmailTemplateInterpolator;
use App\Mail\RenderedEmailTemplate;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Schema;

class RenderEmailTemplateAction
{
    public function __construct(
        protected EmailTemplateInterpolator $interpolator,
    ) {}

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function __invoke(EmailTemplateKey $key, array $replacements = [], ?string $actionUrl = null): RenderedEmailTemplate
    {
        $definition = EmailTemplateCatalog::definition($key);
        $stored = $this->stored($key);

        $replacements = $this->normaliseReplacements($replacements);

        return new RenderedEmailTemplate(
            subject: $this->interpolator->interpolate($stored?->subject ?? $definition['subject'], $replacements),
            greeting: $this->interpolator->interpolate((string) ($stored?->greeting ?? $definition['greeting']), $replacements),
            body: $this->interpolator->interpolate($stored?->body ?? $definition['body'], $replacements),
            salutation: $this->interpolator->interpolate((string) ($stored?->salutation ?? $definition['salutation']), $replacements),
            actionText: $this->nullableInterpolate($stored?->action_text ?? $definition['action_text'], $replacements),
            actionUrl: $actionUrl,
        );
    }

    private function stored(EmailTemplateKey $key): ?EmailTemplate
    {
        if (! Schema::hasTable('email_templates')) {
            return null;
        }

        return EmailTemplate::query()->where('key', $key->value)->first();
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     * @return array<string, scalar|null>
     */
    private function normaliseReplacements(array $replacements): array
    {
        $normalised = [];

        foreach ($replacements as $key => $value) {
            $normalised[strtolower((string) $key)] = $value;
        }

        return $normalised;
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    private function nullableInterpolate(?string $text, array $replacements): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $rendered = trim($this->interpolator->interpolate($text, $replacements));

        return $rendered === '' ? null : $rendered;
    }
}
