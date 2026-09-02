<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Mail\EmailTemplateCatalog;
use App\Models\EmailTemplate;

class SyncEmailTemplatesAction
{
    /**
     * Insert catalog keys that are not yet stored. Never overwrites staff edits.
     */
    public function __invoke(): void
    {
        foreach (EmailTemplateCatalog::definitions() as $definition) {
            EmailTemplate::query()->firstOrCreate(
                ['key' => $definition['key']],
                [
                    'subject' => $definition['subject'],
                    'greeting' => $definition['greeting'],
                    'body' => $definition['body'],
                    'salutation' => $definition['salutation'],
                    'action_text' => $definition['action_text'],
                ],
            );
        }
    }
}
