<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $definition = EmailTemplateCatalog::definition(EmailTemplateKey::LearnerWelcome);

        return [
            'key' => EmailTemplateKey::LearnerWelcome->value,
            'subject' => $definition['subject'],
            'greeting' => $definition['greeting'],
            'body' => $definition['body'],
            'salutation' => $definition['salutation'],
            'action_text' => $definition['action_text'],
        ];
    }

    public function forKey(EmailTemplateKey $key): static
    {
        $definition = EmailTemplateCatalog::definition($key);

        return $this->state(fn (array $attributes): array => [
            'key' => $key->value,
            'subject' => $definition['subject'],
            'greeting' => $definition['greeting'],
            'body' => $definition['body'],
            'salutation' => $definition['salutation'],
            'action_text' => $definition['action_text'],
        ]);
    }
}
