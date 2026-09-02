<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Models\EmailTemplate;

class UpdateEmailTemplateAction
{
    /**
     * @param  array{subject: string, greeting: ?string, body: string, salutation: ?string, action_text: ?string}  $data
     */
    public function __invoke(EmailTemplate $emailTemplate, array $data): EmailTemplate
    {
        $emailTemplate->fill([
            'subject' => $data['subject'],
            'greeting' => $data['greeting'] ?? '',
            'body' => $data['body'],
            'salutation' => $data['salutation'] ?? '',
            'action_text' => $data['action_text'] ?: null,
        ]);
        $emailTemplate->save();

        return $emailTemplate->refresh();
    }
}
