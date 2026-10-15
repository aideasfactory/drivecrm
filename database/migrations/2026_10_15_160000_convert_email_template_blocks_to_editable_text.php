<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates used to receive pre-built sentences and blocks (e.g.
     * {{intro}}, {{payment_block}}, {{refund_line}}) from code, so that copy
     * could not be edited in the admin. Each block token is replaced with the
     * equivalent editable text built from simple placeholders and
     * {{#if condition}}…{{else}}…{{/if}} sections, so stored emails (default
     * or staff-edited) keep reading the same.
     *
     * @var array<string, array<string, string>>
     */
    private array $replacements = [
        'learner.order_confirmation' => [
            'intro' => '{{#if booked_by_contact}}Great news! You have successfully booked driving lessons for **{{learner_name}}**.{{else}}Great news! Your driving lessons have been successfully booked.{{/if}}',
            'first_lesson_line' => '{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}',
            'payment_block' => "{{#if paid_in_full}}**Payment — paid in full:**\n{{cost_breakdown}}\n**Total paid: {{total}}**{{else}}**Payment — weekly:**\n{{cost_breakdown}}\n**Total: {{total}}**\nPaid in {{lessons_count}} weekly instalments of {{weekly_instalment}}{{#if guarantee_on_first_payment}}\n(Pass Your Test Guarantee added to your first weekly payment){{/if}}{{/if}}",
            'booked_for_line' => "{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}",
        ],
        'learner.payment_link' => [
            'intro' => '{{#if booked_by_staff}}Our bookings team has booked driving lessons for {{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}} with **{{instructor_name}}**. Please complete the payment to confirm the booking.{{else}}Your instructor **{{instructor_name}}** has booked driving lessons for {{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}}. Please complete the payment to confirm the booking.{{/if}}',
            'amount_label' => '{{#if pay_weekly}}First week{{else}}Total{{/if}}',
            'first_lesson_line' => '{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}',
            'pay_by_line' => "{{#if pay_by}}Please pay by **{{pay_by}}**. If payment isn't made by then, the lessons will be released and offered to other learners.{{else}}The lessons are held for you until payment is made.{{/if}}{{#if pay_weekly}}\n\nAfter the first week, each lesson is invoiced by email and due {{weekly_payment_due_hours}} hours before it takes place.{{/if}}",
            'booked_for_line' => "{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}",
        ],
        'learner.payment_due_soon' => [
            'intro' => "This is a reminder that payment is due for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.",
        ],
        'learner.lesson_payment_reminder' => [
            'intro' => "This is a friendly reminder that payment is required for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.",
        ],
        'learner.lesson_payment_received' => [
            'intro' => "We've received your payment of **{{amount}}** for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.",
        ],
        'learner.booking_cancelled' => [
            'lesson_noun' => '{{#if multiple_lessons}}Lessons Have{{else}}Lesson Has{{/if}}',
            'lesson_word' => '{{#if multiple_lessons}}lessons have{{else}}lesson has{{/if}}',
            'refund_line' => '{{#if refund_due}}{{#if late_cancellation_charged}}Some of these lessons were cancelled less than {{late_notice_hours}} hours before they were due to start, so what you paid for those is not refundable. You will be refunded what you paid for the others, including the digital fee and any Pass Your Test Guarantee. The booking fee is non-refundable. Our head office will be in touch about this shortly.{{else}}You will be refunded what you paid for these lessons, including the digital fee and any Pass Your Test Guarantee. The booking fee is non-refundable. Our head office will be in touch about this shortly.{{/if}}{{else}}{{#if late_cancellation_charged}}This was cancelled less than {{late_notice_hours}} hours before the lesson was due to start, so what you paid is not refundable.{{else}}There is nothing further you need to do, and you will not be charged for these lessons.{{/if}}{{/if}}',
        ],
        'learner.lesson_signed_off' => [
            'lesson_time_line' => '{{#if lesson_time}}Lesson time: {{lesson_time}}{{/if}}',
            'instructor_notes_block' => "{{#if instructor_notes}}**Instructor Notes:**\n{{instructor_notes}}{{/if}}",
        ],
        'learner.lesson_rescheduled' => [
            'notes_block' => "{{#if notes}}\n**Notes from your instructor:**\n{{notes}}{{/if}}",
        ],
        'learner.instructor_on_way' => [
            'lesson_when' => '{{#if lesson_date}} on {{lesson_date}}{{#if lesson_time}} at {{lesson_time}}{{/if}}{{/if}}',
        ],
        'learner.lesson_feedback_request' => [
            'lesson_time' => '{{#if lesson_time}} ({{lesson_time}}){{/if}}',
        ],
        'instructor.student_gained' => [
            'lessons_block' => "{{#if moved_lessons_count}}{{#if multiple_lessons}}The following {{moved_lessons_count}} lessons have been added to your diary at their existing dates and times:{{else}}The following lesson has been added to your diary at its existing date and time:{{/if}}\n{{lesson_list}}{{#if clash_count}}\n\n⚠️ **{{clash_count}} {{#if multiple_clashes}}clashes{{else}}clash{{/if}} flagged above.** Please review your diary and rebook the affected lessons at alternative times that suit you and the student.{{/if}}{{else}}No future lessons were on the previous instructor’s diary, so nothing has been added to yours yet. The student will book new lessons with you as normal.{{/if}}",
        ],
        'instructor.student_lost' => [
            'lessons_line' => '{{#if lessons_removed}}{{#if multiple_lessons}}{{lessons_removed}} future lessons have been removed from your diary.{{else}}1 future lesson has been removed from your diary.{{/if}}{{else}}No future lessons were on your diary at the time of transfer.{{/if}}',
        ],
        'instructor.lesson_signed_off' => [
            'lesson_time_line' => '{{#if lesson_time}}Lesson time: {{lesson_time}}{{/if}}',
            'payout_line' => '{{#if payout_initiated}}The payout for this lesson has been initiated to your account.{{/if}}',
        ],
        'instructor.year_end_archive_ready' => [
            'file_size' => '{{#if file_size}} ({{file_size}}){{/if}}',
        ],
    ];

    /**
     * @var list<string>
     */
    private array $columns = ['subject', 'greeting', 'body', 'salutation', 'action_text'];

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->replacements as $key => $tokens) {
            $this->convert($key, function (string $text) use ($tokens): string {
                return (string) preg_replace_callback(
                    '/\{\{\{?\s*([a-z0-9_]+)\s*\}?\}\}/i',
                    fn (array $matches): string => $tokens[strtolower($matches[1])] ?? $matches[0],
                    $text,
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->replacements as $key => $tokens) {
            $reverse = [];

            foreach ($tokens as $token => $replacement) {
                $reverse[$replacement] = '{{'.$token.'}}';
            }

            $this->convert($key, fn (string $text): string => strtr($text, $reverse));
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
