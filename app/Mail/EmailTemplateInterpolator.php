<?php

declare(strict_types=1);

namespace App\Mail;

final class EmailTemplateInterpolator
{
    private const CONDITIONAL_TAG = '/(\{\{\{?\s*(?:#if\s+[a-z0-9_]+|else|\/if)\s*\}?\}\})/i';

    private const PLACEHOLDER = '/\{\{\{?\s*([a-z0-9_]+)\s*\}?\}\}/i';

    /**
     * Render `{{placeholder}}` tokens and `{{#if name}}…{{else}}…{{/if}}`
     * sections (which may be nested).
     *
     * Rendering never fails on staff-edited copy: unknown placeholders become
     * empty strings, unknown conditions are treated as false, and unbalanced
     * `{{#if}}` / `{{else}}` / `{{/if}}` tags are dropped. Triple-brace tokens
     * such as `{{{name}}}` are treated the same as `{{name}}`.
     *
     * `{{action_button}}` is left in place for the mail builder to split on.
     *
     * @param  array<string, scalar|null>  $replacements
     */
    public function interpolate(string $text, array $replacements): string
    {
        $normalised = [];

        foreach ($replacements as $key => $value) {
            $normalised[strtolower((string) $key)] = $value;
        }

        $text = $this->renderConditionals($text, $normalised);

        $text = (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $matches) use ($normalised): string {
                $key = strtolower($matches[1]);

                if ($key === 'action_button') {
                    return '{{action_button}}';
                }

                $value = $normalised[$key] ?? null;

                if ($value === null || is_bool($value)) {
                    return '';
                }

                return (string) $value;
            },
            $text,
        );

        return (string) preg_replace('/\n[ \t]*\n(?:[ \t]*\n)+/', "\n\n", $text);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    private function renderConditionals(string $text, array $replacements): string
    {
        if (! preg_match(self::CONDITIONAL_TAG, $text)) {
            return $text;
        }

        $parts = preg_split(self::CONDITIONAL_TAG, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $matched = $this->matchedTagIndexes($parts);

        $output = '';

        /** @var list<array{condition: bool, in_else: bool}> $stack */
        $stack = [];

        foreach ($parts as $index => $part) {
            $isTag = $index % 2 === 1;

            if (! $isTag) {
                if ($this->isActive($stack)) {
                    $output .= $part;
                }

                continue;
            }

            if (! isset($matched[$index])) {
                continue;
            }

            $tag = $this->parseTag($part);

            if ($tag['type'] === 'if') {
                $stack[] = [
                    'condition' => $this->isTruthy($replacements[$tag['name']] ?? null),
                    'in_else' => false,
                ];
            } elseif ($tag['type'] === 'else') {
                $stack[count($stack) - 1]['in_else'] = true;
            } else {
                array_pop($stack);
            }
        }

        return $output;
    }

    /**
     * Indexes (within the split parts) of tags that belong to a balanced
     * `{{#if}}…{{/if}}` pair, including at most one `{{else}}` per pair.
     *
     * @param  list<string>  $parts
     * @return array<int, true>
     */
    private function matchedTagIndexes(array $parts): array
    {
        $matched = [];

        /** @var list<array{open: int, else: ?int}> $open */
        $open = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                continue;
            }

            $tag = $this->parseTag($part);

            if ($tag['type'] === 'if') {
                $open[] = ['open' => $index, 'else' => null];

                continue;
            }

            if ($open === []) {
                continue;
            }

            $top = count($open) - 1;

            if ($tag['type'] === 'else') {
                if ($open[$top]['else'] === null) {
                    $open[$top]['else'] = $index;
                }

                continue;
            }

            $frame = array_pop($open);
            $matched[$frame['open']] = true;
            $matched[$index] = true;

            if ($frame['else'] !== null) {
                $matched[$frame['else']] = true;
            }
        }

        return $matched;
    }

    /**
     * @return array{type: 'if'|'else'|'end', name: string}
     */
    private function parseTag(string $tag): array
    {
        $inner = strtolower(trim($tag, "{} \t\n\r"));

        if (str_starts_with($inner, '#if')) {
            return ['type' => 'if', 'name' => trim(substr($inner, 3))];
        }

        return ['type' => $inner === 'else' ? 'else' : 'end', 'name' => ''];
    }

    /**
     * @param  list<array{condition: bool, in_else: bool}>  $stack
     */
    private function isActive(array $stack): bool
    {
        foreach ($stack as $frame) {
            if ($frame['condition'] === $frame['in_else']) {
                return false;
            }
        }

        return true;
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' && $value !== '0';
        }

        return (bool) $value;
    }
}
