<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Written-out markup → automatic placeholder (longest first). Each
     * placeholder renders exactly what its markup did, so this is safe on
     * staff-edited copy too.
     *
     * @var array<string, string>
     */
    private const MARKUP_TO_PLACEHOLDER = array (
  '{{#if has_fees}}{{#if pay_weekly}}{{#if has_fees}}**Cost breakdown:**{{#if lesson_cost}}
Lesson cost: {{lesson_cost}}{{/if}}{{#if booking_fee}}
Booking fee (weekly instalment): {{booking_fee}}{{/if}}{{#if digital_fee}}
Digital services fee (weekly instalment): {{digital_fee}}{{/if}}{{#if guarantee_fee}}
Pass Your Test Guarantee: {{guarantee_fee}}{{/if}}
{{/if}}{{else}}Lessons: {{lesson_cost}}{{#if booking_fee}}
Booking fee: {{booking_fee}}{{/if}}{{#if digital_fee}}
Digital fee: {{digital_fee}}{{/if}}{{#if guarantee_fee}}
Pass Your Test Guarantee: {{guarantee_fee}}{{/if}}{{#if guarantee_included_free}}
Pass Your Test Guarantee: Included free{{/if}}{{/if}}{{/if}}' => '{{amount_due_breakdown}}',
  '{{#if has_fees}}**Cost breakdown:**{{#if lesson_cost}}
Lesson cost: {{lesson_cost}}{{/if}}{{#if booking_fee}}
Booking fee (weekly instalment): {{booking_fee}}{{/if}}{{#if digital_fee}}
Digital services fee (weekly instalment): {{digital_fee}}{{/if}}{{#if guarantee_fee}}
Pass Your Test Guarantee: {{guarantee_fee}}{{/if}}
{{/if}}' => '{{payment_cost_lines}}',
  'Lessons: {{lesson_cost}}{{#if booking_fee}}
Booking fee: {{booking_fee}}{{/if}}{{#if digital_fee}}
Digital fee: {{digital_fee}}{{/if}}{{#if guarantee_fee}}
Pass Your Test Guarantee: {{guarantee_fee}}{{/if}}{{#if guarantee_included_free}}
Pass Your Test Guarantee: Included free{{/if}}' => '{{order_cost_lines}}',
  '{{#if lesson_details}}
**Your lessons:**
{{lesson_details}}{{else}}{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}{{/if}}' => '{{booked_lessons}}',
  '{{#if booked_by_contact}}
This booking was made for: **{{learner_name}}**{{/if}}' => '{{booked_for_line}}',
  '{{#if booked_by_contact}}**{{learner_name}}\'s**{{else}}your{{/if}}' => '{{your_or_learners}}',
  '{{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}}' => '{{you_or_learner}}',
  '{{#if pay_weekly}}First week{{else}}Total{{/if}}' => '{{amount_due_label}}',
);

    /**
     * Payment emails that gain the "alternative payment" wording: the default
     * before (with automatic placeholders) and after. Only copy still on the
     * old default is moved to the new one.
     *
     * @var array<string, string>
     */
    private const PREVIOUS_DEFAULTS = array (
  'learner.payment_link' => '{{#if booked_by_staff}}Our bookings team has booked driving lessons for {{you_or_learner}} with **{{instructor_name}}**. Please complete the payment to confirm the booking.{{else}}Your instructor **{{instructor_name}}** has booked driving lessons for {{you_or_learner}}. Please complete the payment to confirm the booking.{{/if}}

**Booking Details:**
Package: {{package_name}}
Number of lessons: {{lessons_count}}
Instructor: {{instructor_name}}
{{amount_due_breakdown}}
{{amount_due_label}}: {{total}}
{{booked_lessons}}

Please complete your payment using the link below to confirm your lessons.

{{action_button}}

{{#if pay_by}}Please pay by **{{pay_by}}**. If payment isn\'t made by then, the lessons will be released and offered to other learners.{{else}}The lessons are held for you until payment is made.{{/if}}{{#if pay_weekly}}

After the first week, each lesson is invoiced by email and due {{weekly_payment_due_hours}} hours before it takes place.{{/if}}
{{booked_for_line}}',
  'learner.payment_due_soon' => 'This is a reminder that payment is due for {{your_or_learners}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.

**Lesson Details:**
Package: {{package_name}}
Date: {{lesson_date}}
Time: {{lesson_time}}
{{payment_cost_lines}}
Amount due: {{amount}}

Your lesson is in less than 48 hours. Please complete your payment using the link below to secure it.

{{action_button}}

If you have already paid, please disregard this email.',
  'learner.lesson_payment_reminder' => 'This is a friendly reminder that payment is required for {{your_or_learners}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.

**Lesson Details:**
Package: {{package_name}}
Date: {{lesson_date}}
Time: {{lesson_time}}
{{payment_cost_lines}}
**Amount due: {{amount}}**

Please complete your payment using the link below to secure your lesson.

{{action_button}}

If you have already paid, please disregard this email.',
);

    /**
     * @var array<string, string>
     */
    private const NEW_DEFAULTS = array (
  'learner.payment_link' => '{{#if booked_by_staff}}Our bookings team has booked driving lessons for {{you_or_learner}} with **{{instructor_name}}**.{{else}}Your instructor **{{instructor_name}}** has booked driving lessons for {{you_or_learner}}.{{/if}}{{#if alternative_payment}}

If you\'ve already paid your instructor directly, or agreed to pay them by cash or bank transfer, you don\'t need to do anything. Your lessons are confirmed.{{/if}}

**Booking Details:**
Package: {{package_name}}
Number of lessons: {{lessons_count}}
Instructor: {{instructor_name}}
{{amount_due_breakdown}}
{{amount_due_label}}: {{total}}
{{booked_lessons}}{{#if alternative_payment}}

**Prefer to pay online?**
You can pay for your lessons securely in the app or using the link below. It takes a couple of minutes, you get a receipt straight away, and there\'s no need to carry cash or set up a transfer.{{else}}

Please complete your payment using the link below to confirm your lessons.{{/if}}

{{action_button}}{{#if alternative_payment}}

Paying online is optional. If you\'d rather keep paying your instructor directly, that\'s fine.{{/if}}{{#if alternative_payment}}{{else}}{{#if pay_by}}

Please pay by **{{pay_by}}**. If payment isn\'t made by then, the lessons will be released and offered to other learners.{{else}}

The lessons are held for you until payment is made.{{/if}}{{/if}}{{#if pay_weekly}}

After the first week, each lesson is invoiced by email and due {{weekly_payment_due_hours}} hours before it takes place.{{/if}}
{{booked_for_line}}',
  'learner.payment_due_soon' => 'This is a reminder that payment is due for {{your_or_learners}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.

**Lesson Details:**
Package: {{package_name}}
Date: {{lesson_date}}
Time: {{lesson_time}}
{{payment_cost_lines}}
Amount due: {{amount}}{{#if alternative_payment}}

If you\'re paying your instructor directly for this lesson, you don\'t need to do anything.{{/if}}{{#if alternative_payment}}

**Prefer to pay online?** You can pay securely using the link below.{{else}}

Your lesson is in less than 48 hours. Please complete your payment using the link below to secure it.{{/if}}

{{action_button}}

If you have already paid, please disregard this email.',
  'learner.lesson_payment_reminder' => 'This is a friendly reminder that payment is required for {{your_or_learners}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.

**Lesson Details:**
Package: {{package_name}}
Date: {{lesson_date}}
Time: {{lesson_time}}
{{payment_cost_lines}}
**Amount due: {{amount}}**{{#if alternative_payment}}

If you\'re paying your instructor directly for this lesson, you don\'t need to do anything.{{/if}}{{#if alternative_payment}}

**Prefer to pay online?** You can pay securely using the link below.{{else}}

Please complete your payment using the link below to secure your lesson.{{/if}}

{{action_button}}

If you have already paid, please disregard this email.',
);

    public function up(): void
    {
        if (! Schema::hasTable("email_templates")) {
            return;
        }

        foreach (DB::table("email_templates")->get(["id", "key", "body"]) as $template) {
            $body = strtr((string) $template->body, self::MARKUP_TO_PLACEHOLDER);

            if ((self::PREVIOUS_DEFAULTS[$template->key] ?? null) === $body) {
                $body = self::NEW_DEFAULTS[$template->key];
            }

            if ($body !== $template->body) {
                DB::table("email_templates")->where("id", $template->id)->update(["body" => $body]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable("email_templates")) {
            return;
        }

        $placeholderToMarkup = array_flip(self::MARKUP_TO_PLACEHOLDER);

        foreach (DB::table("email_templates")->get(["id", "key", "body"]) as $template) {
            $body = (string) $template->body;

            if ((self::NEW_DEFAULTS[$template->key] ?? null) === $body) {
                $body = self::PREVIOUS_DEFAULTS[$template->key];
            }

            $body = strtr($body, $placeholderToMarkup);

            if ($body !== $template->body) {
                DB::table("email_templates")->where("id", $template->id)->update(["body" => $body]);
            }
        }
    }
};
