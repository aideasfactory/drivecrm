<?php

declare(strict_types=1);

namespace App\Mail;

final class EmailTemplateInterpolator
{
    /**
     * Replace `{{placeholder}}` tokens. Unknown placeholders become empty
     * strings so incomplete data cannot leak token names to recipients.
     *
     * `{{action_button}}` is left in place for the mail builder to split on.
     *
     * @param  array<string, scalar|null>  $replacements
     */
    public function interpolate(string $text, array $replacements): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function (array $matches) use ($replacements): string {
                $key = strtolower($matches[1]);

                if ($key === 'action_button') {
                    return $matches[0];
                }

                if (! array_key_exists($key, $replacements) && ! array_key_exists($matches[1], $replacements)) {
                    return '';
                }

                $value = $replacements[$key] ?? $replacements[$matches[1]] ?? '';

                if ($value === null) {
                    return '';
                }

                return (string) $value;
            },
            $text,
        );
    }
}
