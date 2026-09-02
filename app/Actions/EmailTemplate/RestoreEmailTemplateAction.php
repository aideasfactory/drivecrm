<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Models\EmailTemplate;

class RestoreEmailTemplateAction
{
    public function __invoke(EmailTemplate $emailTemplate): EmailTemplate
    {
        $definition = EmailTemplateCatalog::definition(
            EmailTemplateKey::from($emailTemplate->key),
        );

        $emailTemplate->fill([
            'subject' => $definition['subject'],
            'greeting' => $definition['greeting'],
            'body' => $definition['body'],
            'salutation' => $definition['salutation'],
            'action_text' => $definition['action_text'],
        ]);
        $emailTemplate->save();

        return $emailTemplate->refresh();
    }
}
