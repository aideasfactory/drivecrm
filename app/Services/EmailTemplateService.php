<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\EmailTemplate\BuildTemplatedMailMessageAction;
use App\Actions\EmailTemplate\ListEmailTemplatesAction;
use App\Actions\EmailTemplate\RenderEmailTemplateAction;
use App\Actions\EmailTemplate\RestoreEmailTemplateAction;
use App\Actions\EmailTemplate\UpdateEmailTemplateAction;
use App\Enums\EmailTemplateKey;
use App\Mail\RenderedEmailTemplate;
use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailTemplateService extends BaseService
{
    public function __construct(
        protected ListEmailTemplatesAction $listEmailTemplates,
        protected UpdateEmailTemplateAction $updateEmailTemplate,
        protected RestoreEmailTemplateAction $restoreEmailTemplate,
        protected RenderEmailTemplateAction $renderEmailTemplate,
        protected BuildTemplatedMailMessageAction $buildTemplatedMailMessage,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return ($this->listEmailTemplates)();
    }

    /**
     * @param  array{subject: string, greeting: ?string, body: string, salutation: ?string, action_text: ?string}  $data
     */
    public function update(EmailTemplate $emailTemplate, array $data): EmailTemplate
    {
        return ($this->updateEmailTemplate)($emailTemplate, $data);
    }

    public function restore(EmailTemplate $emailTemplate): EmailTemplate
    {
        return ($this->restoreEmailTemplate)($emailTemplate);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function render(EmailTemplateKey $key, array $replacements = [], ?string $actionUrl = null): RenderedEmailTemplate
    {
        return ($this->renderEmailTemplate)($key, $replacements, $actionUrl);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function toMailMessage(EmailTemplateKey $key, array $replacements = [], ?string $actionUrl = null): MailMessage
    {
        return ($this->buildTemplatedMailMessage)($this->render($key, $replacements, $actionUrl));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(RenderedEmailTemplate $rendered): array
    {
        [$before, $after] = $this->splitOnActionButton($rendered->body);

        return [
            'subject' => $rendered->subject,
            'greeting' => $rendered->greeting,
            'bodyHtml' => $this->markdownToHtml($before.($after ?? '')),
            'bodyHtmlBefore' => $this->markdownToHtml($before),
            'bodyHtmlAfter' => $after === null ? '' : $this->markdownToHtml($after),
            'actionText' => $rendered->actionText,
            'actionUrl' => $rendered->actionUrl,
            'hasActionSplit' => $after !== null,
            'salutationHtml' => $this->markdownToHtml((string) $rendered->salutation),
            'appName' => config('app.name'),
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitOnActionButton(string $body): array
    {
        if (! preg_match('/\{\{\s*action_button\s*\}\}/i', $body)) {
            return [$body, null];
        }

        $parts = preg_split('/\{\{\s*action_button\s*\}\}/i', $body, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function markdownToHtml(string $markdown): string
    {
        $markdown = trim($markdown);

        if ($markdown === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
