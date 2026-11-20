<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Wording the system works out from an email's other values, so staff never
 * edit the logic behind it: "you" or the learner's name, the cost lines, the
 * booked lesson list. Each is rendered from its markup with the same values
 * the email is sent with, so an email using `{{you_or_learner}}` reads exactly
 * as one with the markup written out in full.
 */
final class EmailTemplateDerivedPlaceholders
{
    /**
     * Order-level cost lines (whole package): lessons, then each fee only when charged.
     */
    public const ORDER_COST_LINES = "Lessons: {{lesson_cost}}{{#if booking_fee}}\nBooking fee: {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital fee: {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}{{#if guarantee_included_free}}\nPass Your Test Guarantee: Included free{{/if}}";

    /**
     * Single-payment cost lines, shown only when the payment carries fees. The
     * trailing newline keeps a blank line before the amount that follows.
     */
    public const PAYMENT_COST_LINES = "{{#if has_fees}}**Cost breakdown:**{{#if lesson_cost}}\nLesson cost: {{lesson_cost}}{{/if}}{{#if booking_fee}}\nBooking fee (weekly instalment): {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital services fee (weekly instalment): {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}\n{{/if}}";

    /**
     * Booked lessons, one per line with instructor, start time, duration and
     * pickup; falls back to the first lesson date when no lessons are listed.
     */
    public const LESSON_DETAILS_LINES = "{{#if lesson_details}}\n**Your lessons:**\n{{lesson_details}}{{else}}{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}{{/if}}";

    public const YOU_OR_LEARNER = '{{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}}';

    public const YOUR_OR_LEARNERS = "{{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}}";

    public const BOOKED_FOR_LINE = "{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}";

    public const AMOUNT_DUE_BREAKDOWN = '{{#if has_fees}}{{#if pay_weekly}}'.self::PAYMENT_COST_LINES.'{{else}}'.self::ORDER_COST_LINES.'{{/if}}{{/if}}';

    public const AMOUNT_DUE_LABEL = '{{#if pay_weekly}}First week{{else}}Total{{/if}}';

    /**
     * @var array<string, array{label: string, markup: string}>
     */
    private const DEFINITIONS = [
        'you_or_learner' => [
            'label' => 'Automatic: "you", or the learner\'s name in bold when a contact (e.g. parent) booked',
            'markup' => self::YOU_OR_LEARNER,
        ],
        'your_or_learners' => [
            'label' => 'Automatic: "your", or the learner\'s name with \'s in bold when a contact booked',
            'markup' => self::YOUR_OR_LEARNERS,
        ],
        'booked_for_line' => [
            'label' => 'Automatic: "This booking was made for: (learner)" on a new line, only when a contact booked',
            'markup' => self::BOOKED_FOR_LINE,
        ],
        'booked_lessons' => [
            'label' => 'Automatic: "Your lessons:" with every booked lesson, or the first lesson date',
            'markup' => self::LESSON_DETAILS_LINES,
        ],
        'order_cost_lines' => [
            'label' => 'Automatic: lessons cost, then each fee only when charged',
            'markup' => self::ORDER_COST_LINES,
        ],
        'payment_cost_lines' => [
            'label' => 'Automatic: cost breakdown for this payment, only when it includes fees',
            'markup' => self::PAYMENT_COST_LINES,
        ],
        'amount_due_breakdown' => [
            'label' => 'Automatic: cost breakdown of the amount due now (weekly or whole order), only when it includes fees',
            'markup' => self::AMOUNT_DUE_BREAKDOWN,
        ],
        'amount_due_label' => [
            'label' => 'Automatic: "First week" when paid weekly, otherwise "Total"',
            'markup' => self::AMOUNT_DUE_LABEL,
        ],
    ];

    public function __construct(
        protected EmailTemplateInterpolator $interpolator,
    ) {}

    /**
     * The values with every automatic placeholder added. A value already
     * supplied by the sender is kept.
     *
     * @param  array<string, scalar|null>  $replacements
     * @return array<string, scalar|null>
     */
    public function with(array $replacements): array
    {
        foreach (self::DEFINITIONS as $name => $definition) {
            if (! array_key_exists($name, $replacements)) {
                $replacements[$name] = $this->interpolator->interpolate($definition['markup'], $replacements);
            }
        }

        return $replacements;
    }

    /**
     * Labels of the automatic placeholders a body uses, for the editor.
     *
     * @return array<string, string>
     */
    public static function labelsUsedIn(string $body): array
    {
        $labels = [];

        foreach (self::DEFINITIONS as $name => $definition) {
            if (str_contains($body, '{{'.$name.'}}')) {
                $labels[$name] = $definition['label'];
            }
        }

        return $labels;
    }

    /**
     * Markup → placeholder, longest first, for moving stored copy onto the
     * automatic placeholders without changing what it renders.
     *
     * @return array<string, string>
     */
    public static function markupReplacements(): array
    {
        $map = [];

        foreach (self::DEFINITIONS as $name => $definition) {
            $map[$definition['markup']] = '{{'.$name.'}}';
        }

        uksort($map, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $map;
    }
}
