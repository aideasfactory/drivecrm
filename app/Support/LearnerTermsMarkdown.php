<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renders Learner Terms Markdown to safe HTML for the CRM preview.
 * Raw HTML in the source is escaped; unsafe links are dropped.
 */
class LearnerTermsMarkdown
{
    public static function toHtml(?string $markdown): string
    {
        return Str::markdown((string) $markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
