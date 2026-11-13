<?php

declare(strict_types=1);

namespace App\Actions\Import;

class ParseReflectiveLogTextAction
{
    /**
     * Headings in a legacy reflective log, mapped to keys.
     */
    public const HEADINGS = [
        'goals for this lesson' => 'goals',
        'feelings' => 'feelings',
        'achieved today' => 'achieved',
        'actions to take' => 'actions',
        'next lesson' => 'next_lesson',
    ];

    /**
     * Split a legacy reflective log into its sections. The text is one line
     * per heading with a JSON list after the colon, e.g.
     * `Goals for this lesson: ["To feel comfortable at roundabouts"]` or
     * `Actions to take: [{"action":"Roundabouts","achieve":null}]`.
     * Each section becomes a list of non-empty strings (an action's `action`
     * text). A line that isn't JSON is kept as plain text.
     *
     * @return array{goals: array<int, string>, feelings: array<int, string>, achieved: array<int, string>, actions: array<int, string>, next_lesson: array<int, string>}
     */
    public function __invoke(string $text): array
    {
        $sections = array_fill_keys(array_values(self::HEADINGS), []);

        // Only real line breaks: a byte-mode \R also splits on 0x85, which is the last byte of many emoji (e.g. 😅).
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            [$heading, $value] = array_pad(explode(':', $line, 2), 2, '');
            $key = self::HEADINGS[strtolower(trim($heading))] ?? null;

            if ($key === null) {
                continue;
            }

            $sections[$key] = $this->items(trim($value));
        }

        return $sections;
    }

    /**
     * Whether every section is empty (nothing worth importing).
     *
     * @param  array<string, array<int, string>>  $sections
     */
    public static function isEmpty(array $sections): bool
    {
        foreach ($sections as $items) {
            if ($items !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function items(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [$value];
        }

        $items = [];

        foreach ($decoded as $item) {
            $text = is_array($item) ? (string) ($item['action'] ?? '') : (string) $item;
            $text = trim($text);

            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }
}
