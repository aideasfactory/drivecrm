<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Enums\EmailTemplateAudience;
use App\Mail\EmailTemplateCatalog;
use App\Models\EmailTemplate;
use Illuminate\Support\Collection;

class ListEmailTemplatesAction
{
    public function __construct(
        protected SyncEmailTemplatesAction $syncEmailTemplates,
    ) {}

    /**
     * @return Collection<int, array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     audience_label: string,
     *     description: string,
     *     placeholders: array<int, array{key: string, label: string}>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string,
     *     is_customised: bool,
     *     updated_at: ?string
     * }>
     */
    public function __invoke(): Collection
    {
        ($this->syncEmailTemplates)();

        $stored = EmailTemplate::query()
            ->get()
            ->keyBy('key');

        return collect(EmailTemplateCatalog::definitions())
            ->map(function (array $definition) use ($stored): array {
                $row = $stored->get($definition['key']);
                $audience = EmailTemplateAudience::from($definition['audience']);

                $subject = $row?->subject ?? $definition['subject'];
                $greeting = $row?->greeting ?? $definition['greeting'];
                $body = $row?->body ?? $definition['body'];
                $salutation = $row?->salutation ?? $definition['salutation'];
                $actionText = $row?->action_text ?? $definition['action_text'];

                return [
                    'key' => $definition['key'],
                    'name' => $definition['name'],
                    'audience' => $audience->value,
                    'audience_label' => $audience->label(),
                    'description' => $definition['description'],
                    'placeholders' => collect($definition['placeholders'])
                        ->map(fn (string $label, string $key): array => [
                            'key' => $key,
                            'label' => $label,
                        ])
                        ->values()
                        ->all(),
                    'subject' => $subject,
                    'greeting' => $greeting,
                    'body' => $body,
                    'salutation' => $salutation,
                    'action_text' => $actionText,
                    'is_customised' => $row !== null && $this->isCustomised($definition, $row),
                    'updated_at' => $row?->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
                ];
            })
            ->sortBy(fn (array $template): string => $template['audience'].$template['name'])
            ->values();
    }

    /**
     * @param  array{subject: string, greeting: string, body: string, salutation: string, action_text: ?string}  $definition
     */
    private function isCustomised(array $definition, EmailTemplate $row): bool
    {
        return $row->subject !== $definition['subject']
            || (string) $row->greeting !== (string) $definition['greeting']
            || $row->body !== $definition['body']
            || (string) $row->salutation !== (string) $definition['salutation']
            || $row->action_text !== $definition['action_text'];
    }
}
