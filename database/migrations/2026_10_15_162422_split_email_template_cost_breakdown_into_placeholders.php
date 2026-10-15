<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORDER_COST_LINES = "Lessons: {{lesson_cost}}{{#if booking_fee}}\nBooking fee: {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital fee: {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}{{#if guarantee_included_free}}\nPass Your Test Guarantee: Included free{{/if}}";

    private const PAYMENT_COST_LINES = "{{#if has_fees}}**Cost breakdown:**{{#if lesson_cost}}\nLesson cost: {{lesson_cost}}{{/if}}{{#if booking_fee}}\nBooking fee (weekly instalment): {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital services fee (weekly instalment): {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}\n{{/if}}";

    private const LESSON_WORD = '{{#if multiple_lessons}}lessons{{else}}lesson{{/if}}';

    /**
     * Columns that can hold template copy.
     *
     * @var list<string>
     */
    private array $columns = ['subject', 'greeting', 'body', 'salutation', 'action_text'];

    /**
     * {{cost_breakdown}} and {{lesson_word}} were the last code-built pieces
     * of copy. Each is replaced with the equivalent editable text built from
     * individual placeholders ({{lesson_cost}}, {{booking_fee}},
     * {{digital_fee}}, {{guarantee_fee}}) and {{#if}} sections, in default or
     * staff-edited rows. The bulk-reschedule learner copy also gets its
     * singular/plural grammar fixed where the default wording is still intact.
     *
     * @return array<string, array{tokens: array<string, string>, phrases: list<array{0: string, 1: string}>}>
     */
    private function conversions(): array
    {
        return [
            'learner.order_confirmation' => [
                'tokens' => ['cost_breakdown' => self::ORDER_COST_LINES],
                'phrases' => [],
            ],
            'learner.payment_link' => [
                'tokens' => ['cost_breakdown' => '{{#if has_fees}}{{#if pay_weekly}}'.self::PAYMENT_COST_LINES.'{{else}}'.self::ORDER_COST_LINES.'{{/if}}{{/if}}'],
                'phrases' => [],
            ],
            'learner.payment_due_soon' => [
                'tokens' => ['cost_breakdown' => self::PAYMENT_COST_LINES],
                'phrases' => [],
            ],
            'learner.lesson_payment_reminder' => [
                'tokens' => ['cost_breakdown' => self::PAYMENT_COST_LINES],
                'phrases' => [],
            ],
            'learner.lesson_payment_received' => [
                'tokens' => ['cost_breakdown' => self::PAYMENT_COST_LINES],
                'phrases' => [],
            ],
            'learner.lessons_bulk_rescheduled' => [
                'tokens' => ['lesson_word' => self::LESSON_WORD],
                'phrases' => [
                    ['Your driving lessons have been rescheduled', 'Your driving {{#if multiple_lessons}}lessons have{{else}}lesson has{{/if}} been rescheduled'],
                    [self::LESSON_WORD.' with **{{instructor_name}}** have been rescheduled.', self::LESSON_WORD.' with **{{instructor_name}}** {{#if multiple_lessons}}have{{else}}has{{/if}} been rescheduled.'],
                ],
            ],
            'instructor.lessons_bulk_rescheduled' => [
                'tokens' => ['lesson_word' => self::LESSON_WORD],
                'phrases' => [],
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->conversions() as $key => ['tokens' => $tokens, 'phrases' => $phrases]) {
            $this->convert($key, function (string $text) use ($tokens, $phrases): string {
                $text = (string) preg_replace_callback(
                    '/\{\{\{?\s*([a-z0-9_]+)\s*\}?\}\}/i',
                    fn (array $matches): string => $tokens[strtolower($matches[1])] ?? $matches[0],
                    $text,
                );

                foreach ($phrases as [$from, $to]) {
                    $text = str_replace($from, $to, $text);
                }

                return $text;
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->conversions() as $key => ['tokens' => $tokens, 'phrases' => $phrases]) {
            $this->convert($key, function (string $text) use ($tokens, $phrases): string {
                foreach (array_reverse($phrases) as [$from, $to]) {
                    $text = str_replace($to, $from, $text);
                }

                $reverse = [];

                foreach ($tokens as $token => $replacement) {
                    $reverse[$replacement] = '{{'.$token.'}}';
                }

                return strtr($text, $reverse);
            });
        }
    }

    /**
     * @param  callable(string): string  $transform
     */
    private function convert(string $key, callable $transform): void
    {
        $templates = DB::table('email_templates')
            ->where('key', $key)
            ->get(['id', ...$this->columns]);

        foreach ($templates as $template) {
            $changes = [];

            foreach ($this->columns as $column) {
                $original = $template->{$column};

                if ($original === null) {
                    continue;
                }

                $converted = $transform((string) $original);

                if ($converted !== $original) {
                    $changes[$column] = $converted;
                }
            }

            if ($changes !== []) {
                DB::table('email_templates')
                    ->where('id', $template->id)
                    ->update($changes);
            }
        }
    }
};
