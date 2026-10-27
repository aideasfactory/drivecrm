<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateKey;
use InvalidArgumentException;

final class EmailTemplateCatalog
{
    /**
     * Order-level cost lines (whole package): lessons, then each fee only when charged.
     */
    private const ORDER_COST_LINES = "Lessons: {{lesson_cost}}{{#if booking_fee}}\nBooking fee: {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital fee: {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}{{#if guarantee_included_free}}\nPass Your Test Guarantee: Included free{{/if}}";

    /**
     * Single-payment cost lines, shown only when the payment carries fees. The
     * trailing newline keeps a blank line before the amount that follows.
     */
    private const PAYMENT_COST_LINES = "{{#if has_fees}}**Cost breakdown:**{{#if lesson_cost}}\nLesson cost: {{lesson_cost}}{{/if}}{{#if booking_fee}}\nBooking fee (weekly instalment): {{booking_fee}}{{/if}}{{#if digital_fee}}\nDigital services fee (weekly instalment): {{digital_fee}}{{/if}}{{#if guarantee_fee}}\nPass Your Test Guarantee: {{guarantee_fee}}{{/if}}\n{{/if}}";

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }
     */
    public static function definition(EmailTemplateKey $key): array
    {
        $definitions = self::definitions();

        if (! isset($definitions[$key->value])) {
            throw new InvalidArgumentException("Unknown email template [{$key->value}].");
        }

        return $definitions[$key->value];
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach ([
            ...self::learnerDefinitions(),
            ...self::instructorDefinitions(),
            ...self::sharedDefinitions(),
            ...self::adminDefinitions(),
        ] as $definition) {
            $definitions[$definition['key']] = $definition;
        }

        return $definitions;
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }>
     */
    private static function learnerDefinitions(): array
    {
        $audience = EmailTemplateAudience::Learner->value;

        return [
            self::entry(EmailTemplateKey::LearnerWelcome, 'Learner welcome (added by instructor)', $audience, 'Sent when an instructor adds a learner and a temporary password is issued.', [
                'recipient_name' => 'Learner name',
                'instructor_name' => 'Instructor name',
                'app_name' => 'Application name',
                'email' => 'Learner login email',
                'temporary_password' => 'Temporary password',
            ], 'Welcome to {{app_name}}!', 'Hello {{recipient_name}}!', "{{instructor_name}} has added you as a student on {{app_name}}.\n\nYour temporary login details are:\n**Email:** {{email}}\n**Password:** {{temporary_password}}\n\n{{action_button}}\n\nPlease change your password the first time you sign in.", 'Thanks, {{app_name}}', 'Download App Now'),

            self::entry(EmailTemplateKey::LearnerRegistered, 'Learner welcome (self-registration)', $audience, 'Sent when a learner creates their own account.', [
                'recipient_name' => 'Learner name',
                'app_name' => 'Application name',
            ], 'Welcome to {{app_name}}!', 'Hello {{recipient_name}}!', "Thanks for joining {{app_name}}! Your account has been created successfully.\n\nYou can now browse instructors, book lessons, and manage your driving journey all in one place.\n\n{{action_button}}\n\nWe're excited to help you on the road to your driving licence!", "Safe driving,\nThe {{app_name}} Team", 'Open App'),

            self::entry(EmailTemplateKey::LearnerOrderConfirmation, 'Lesson booking confirmation', $audience, 'Sent after a learner (or their contact) books a package.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'first_lesson_date' => 'First lesson date (blank if none booked yet)',
                'lesson_cost' => 'Lessons cost, excluding fees',
                'booking_fee' => 'Booking fee (blank when none)',
                'digital_fee' => 'Digital fee (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge (blank when none or free)',
                'total' => 'Order total',
                'weekly_instalment' => 'Regular weekly instalment (blank when paid in full)',
                'app_name' => 'Application name',
            ], 'Your Driving Lessons Have Been Booked!', 'Hello {{recipient_name}}!', "{{#if booked_by_contact}}Great news! You have successfully booked driving lessons for **{{learner_name}}**.{{else}}Great news! Your driving lessons have been successfully booked.{{/if}}\n\n**Order Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}\n\n{{#if paid_in_full}}**Payment — paid in full:**\n".self::ORDER_COST_LINES."\n**Total paid: {{total}}**{{else}}**Payment — weekly:**\n".self::ORDER_COST_LINES."\n**Total: {{total}}**\nPaid in {{lessons_count}} weekly instalments of {{weekly_instalment}}{{#if guarantee_on_first_payment}}\n(Pass Your Test Guarantee added to your first weekly payment){{/if}}{{/if}}\n\n**Next Steps:**\n1. Download the app to view your lesson schedule\n2. Your instructor will contact you to confirm the details\n3. Make sure to arrive 5 minutes early for your first lesson\n{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}\n\n{{action_button}}\n\nThank you for choosing us for your driving lessons!", "Safe driving,\nThe Driving School Team", 'Download app', [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
                'paid_in_full' => 'The order was paid in full up front',
                'pay_weekly' => 'The order is paid weekly',
                'guarantee_on_first_payment' => 'Weekly order with a paid Pass Your Test Guarantee on the first payment',
                'guarantee_included_free' => 'The Pass Your Test Guarantee is included free',
            ]),

            self::entry(EmailTemplateKey::LearnerCashBookingConfirmed, 'Cash booking confirmation', $audience, 'Sent when head office books lessons that the learner pays for in cash to their instructor.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'first_lesson_date' => 'First lesson date (blank if none booked yet)',
                'lesson_price' => 'Cash to pay the instructor at each lesson',
                'total' => 'Total for all lessons in the booking',
                'app_name' => 'Application name',
            ], 'Your Driving Lessons Have Been Booked', 'Hello {{recipient_name}}!', "{{#if booked_by_contact}}Driving lessons have been booked for **{{learner_name}}** with **{{instructor_name}}**.{{else}}Your driving lessons with **{{instructor_name}}** have been booked.{{/if}} They are paid in cash to the instructor at each lesson, so there is nothing to pay online.\n\n**Booking Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}\n\n**Paying in cash — each lesson:**\nLesson: {{lesson_price}}\nBooking fee: £0.00\nDigital fee: £0.00\n**Pay your instructor {{lesson_price}} in cash at each lesson**\n\nTotal for all {{lessons_count}} lessons: {{total}}\n\n**Next Steps:**\n1. Download the app to view your lesson schedule\n2. Have the cash ready for your instructor at each lesson\n3. Make sure to arrive 5 minutes early for your first lesson\n{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}\n\n{{action_button}}\n\nIf you have any questions, please get in touch with your instructor.", "Safe driving,\nThe Driving School Team", 'Download app', [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
            ]),

            self::entry(EmailTemplateKey::LearnerPaymentLink, 'Payment link for booked lessons', $audience, 'Sent when an instructor books lessons that still need paying.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'lesson_cost' => 'Lessons cost due now, excluding fees (whole order, or the first week)',
                'booking_fee' => 'Booking fee due now (blank when none)',
                'digital_fee' => 'Digital fee due now (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge due now (blank when none or free)',
                'total' => 'Amount to pay now, including fees (whole order, or the first week)',
                'first_lesson_date' => 'First lesson date (blank if none booked yet)',
                'pay_by' => 'Payment deadline before the lessons are released (blank if held until paid)',
                'weekly_payment_due_hours' => 'Hours before each weekly lesson its payment is due',
            ], 'Complete Your Payment for Driving Lessons', 'Hello {{recipient_name}}!', "{{#if booked_by_staff}}Our bookings team has booked driving lessons for {{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}} with **{{instructor_name}}**. Please complete the payment to confirm the booking.{{else}}Your instructor **{{instructor_name}}** has booked driving lessons for {{#if booked_by_contact}}**{{learner_name}}**{{else}}you{{/if}}. Please complete the payment to confirm the booking.{{/if}}\n\n**Booking Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{#if has_fees}}{{#if pay_weekly}}".self::PAYMENT_COST_LINES.'{{else}}'.self::ORDER_COST_LINES."{{/if}}{{/if}}\n{{#if pay_weekly}}First week{{else}}Total{{/if}}: {{total}}\n{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}\n\nPlease complete your payment using the link below to confirm your lessons.\n\n{{action_button}}\n\n{{#if pay_by}}Please pay by **{{pay_by}}**. If payment isn't made by then, the lessons will be released and offered to other learners.{{else}}The lessons are held for you until payment is made.{{/if}}{{#if pay_weekly}}\n\nAfter the first week, each lesson is invoiced by email and due {{weekly_payment_due_hours}} hours before it takes place.{{/if}}\n{{#if booked_by_contact}}\nThis booking was made for: **{{learner_name}}**{{/if}}", "Safe driving,\nThe Driving School Team", 'Pay Now', [
                'booked_by_contact' => 'A contact (e.g. parent) is paying on behalf of the learner',
                'booked_by_staff' => 'The head-office bookings team made the booking',
                'pay_weekly' => 'The order is paid weekly (the amount due now is the first week)',
                'has_fees' => 'The amount due now includes a booking fee, digital fee or guarantee',
                'guarantee_included_free' => 'The Pass Your Test Guarantee is included free (paid in full only)',
            ]),

            self::entry(EmailTemplateKey::LearnerPaymentDueSoon, 'Payment due soon (48 hours)', $audience, 'Reminder that a weekly lesson payment is due in under 48 hours.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'lesson_cost' => 'Lesson cost in this payment, excluding fees',
                'booking_fee' => 'Booking fee share in this payment (blank when none)',
                'digital_fee' => 'Digital fee share in this payment (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge in this payment (blank when none)',
                'amount' => 'Amount due',
            ], 'Payment Due Soon: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "This is a reminder that payment is due for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\n".self::PAYMENT_COST_LINES."\nAmount due: {{amount}}\n\nYour lesson is in less than 48 hours. Please complete your payment using the link below to secure it.\n\n{{action_button}}\n\nIf you have already paid, please disregard this email.", "Safe driving,\nThe Driving School Team", 'Pay Now', [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
                'has_fees' => 'This payment includes a booking fee, digital fee or guarantee',
            ]),

            self::entry(EmailTemplateKey::LearnerBookingCancelled, 'Booking cancelled', $audience, 'Sent to the learner when lessons are cancelled.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_count' => 'Number of cancelled lessons',
                'lesson_list' => 'Bulleted list of cancelled lessons',
                'reason' => 'Cancellation reason (and any details the canceller added)',
                'app_name' => 'Application name',
            ], 'Your Driving {{#if multiple_lessons}}Lessons Have{{else}}Lesson Has{{/if}} Been Cancelled', 'Hello {{recipient_name}},', "We're letting you know that the following {{#if multiple_lessons}}lessons have{{else}}lesson has{{/if}} been cancelled by **{{instructor_name}}**:\n\n{{lesson_list}}\n\n**Reason:**\n{{reason}}\n\n{{#if refund_due}}Our head office will be in touch shortly about what you paid for {{#if multiple_lessons}}these lessons{{else}}this lesson{{/if}}.{{else}}There is nothing further you need to do, and you will not be charged for these lessons.{{/if}}\n\nIf you have any questions, please get in touch with your instructor.", "Kind regards,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one lesson was cancelled',
                'refund_due' => 'Some or all of the lessons were paid for (Head Office will be in touch)',
            ]),

            self::entry(EmailTemplateKey::LearnerLessonSignedOff, 'Lesson signed off (learner)', $audience, 'Sent to the learner after their instructor signs off a lesson.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time range (blank if not set)',
                'instructor_notes' => 'Instructor notes / lesson summary (blank if none)',
                'app_name' => 'Application name',
            ], 'Your Driving Lesson Has Been Signed Off', 'Hello {{recipient_name}}!', "Great news! Your driving lesson on **{{lesson_date}}** has been signed off by {{instructor_name}}.\n{{#if lesson_time}}Lesson time: {{lesson_time}}{{/if}}\n{{#if instructor_notes}}**Instructor Notes:**\n{{instructor_notes}}{{/if}}\n\nKeep up the great work on your driving journey!", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonsBulkRescheduled, 'Lessons bulk-rescheduled (learner)', $audience, 'Sent when an instructor moves a run of upcoming lessons to a new weekly slot.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'start_date' => 'First new lesson date',
                'day_of_week' => 'New weekday',
                'time' => 'New time range',
                'total_lessons' => 'How many lessons moved',
                'app_name' => 'Application name',
            ], 'Your driving {{#if multiple_lessons}}lessons have{{else}}lesson has{{/if}} been rescheduled', 'Hello {{recipient_name}},', "Your upcoming {{#if multiple_lessons}}lessons{{else}}lesson{{/if}} with **{{instructor_name}}** {{#if multiple_lessons}}have{{else}}has{{/if}} been rescheduled.\n\nFrom **{{start_date}}**, you will now have your lessons on **{{day_of_week}}s at {{time}}**.\n\nTotal {{#if multiple_lessons}}lessons{{else}}lesson{{/if}} moved: **{{total_lessons}}**.\n\nIf this new schedule does not work for you, please contact your instructor to arrange alternatives.", "Safe driving,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one lesson was moved',
            ]),

            self::entry(EmailTemplateKey::LearnerLessonRescheduled, 'Single lesson rescheduled', $audience, 'Sent when one lesson is moved to a new date or time.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'old_when' => 'Previous date and time',
                'old_date' => 'Previous date',
                'old_time' => 'Previous time range',
                'new_when' => 'New date and time',
                'new_date' => 'New date',
                'new_time' => 'New time range (blank if not set)',
                'notes' => 'Notes from the instructor (blank if none)',
                'app_name' => 'Application name',
            ], 'Your Driving Lesson Has Been Rescheduled', 'Hello {{recipient_name}}!', "Your driving lesson with **{{instructor_name}}** has been rescheduled.\n\n**Previous:**\n{{old_when}}\n\n**New:**\n{{new_when}}\n{{#if notes}}\n**Notes from your instructor:**\n{{notes}}{{/if}}\n\nIf this new time does not work for you, please contact your instructor to arrange an alternative.", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonsBanked, 'Lessons kept to rebook later', $audience, 'Sent when an instructor takes lessons out of the diary but keeps them in the booking to rebook later.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_count' => 'Number of lessons kept to rebook',
                'lesson_list' => 'Bulleted list of the lessons as they were booked',
                'package_name' => 'Package name',
                'reason' => 'Reason (and any details the instructor added)',
                'app_name' => 'Application name',
            ], 'Your Driving {{#if multiple_lessons}}Lessons{{else}}Lesson{{/if}} Will Be Rebooked', 'Hello {{recipient_name}},', "**{{instructor_name}}** can no longer take the following {{#if multiple_lessons}}lessons{{else}}lesson{{/if}} at the booked time:\n\n{{lesson_list}}\n\n**Reason:**\n{{reason}}\n\nDon't worry: {{#if multiple_lessons}}these lessons stay{{else}}this lesson stays{{/if}} in your {{package_name}} booking at the price you booked. Nothing has been cancelled and you won't be charged again. Your instructor will rebook {{#if multiple_lessons}}them{{else}}it{{/if}} with you and you'll get an email with the new date.\n\nIf you have any questions, please get in touch with your instructor.", "Kind regards,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one lesson was kept to rebook',
            ]),

            self::entry(EmailTemplateKey::LearnerBankedLessonRebooked, 'Kept lesson rebooked', $audience, 'Sent when an instructor rebooks a lesson that was kept in the booking to rebook later.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'new_when' => 'New date and time',
                'new_date' => 'New date',
                'new_time' => 'New time range',
                'package_name' => 'Package name',
                'app_name' => 'Application name',
            ], 'Your Driving Lesson Has Been Rebooked', 'Hello {{recipient_name}}!', "Good news! Your {{package_name}} lesson with **{{instructor_name}}** has been rebooked for:\n\n**{{new_when}}**\n\nIt is part of your existing booking at the price you booked.\n\nIf this time does not work for you, please contact your instructor to arrange an alternative.", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonPaymentReminder, 'Lesson payment required', $audience, 'Sent when a weekly lesson invoice is waiting to be paid.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'lesson_cost' => 'Lesson cost in this payment, excluding fees',
                'booking_fee' => 'Booking fee share in this payment (blank when none)',
                'digital_fee' => 'Digital fee share in this payment (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge in this payment (blank when none)',
                'amount' => 'Amount due',
            ], 'Payment Required: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "This is a friendly reminder that payment is required for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} upcoming driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\n".self::PAYMENT_COST_LINES."\n**Amount due: {{amount}}**\n\nPlease complete your payment using the link below to secure your lesson.\n\n{{action_button}}\n\nIf you have already paid, please disregard this email.", "Safe driving,\nThe Driving School Team", 'Pay Now', [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
                'has_fees' => 'This payment includes a booking fee, digital fee or guarantee',
            ]),

            self::entry(EmailTemplateKey::LearnerLessonPaymentReceived, 'Lesson payment confirmed', $audience, 'Sent when a weekly lesson payment succeeds.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'instructor_name' => 'Instructor name',
                'lesson_cost' => 'Lesson cost in this payment, excluding fees',
                'booking_fee' => 'Booking fee share in this payment (blank when none)',
                'digital_fee' => 'Digital fee share in this payment (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge in this payment (blank when none)',
                'amount' => 'Amount paid',
            ], 'Payment Confirmed: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "We've received your payment of **{{amount}}** for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} driving lesson on **{{lesson_date}}** at **{{lesson_time}}**.\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nInstructor: {{instructor_name}}\n".self::PAYMENT_COST_LINES."\nAmount paid: {{amount}}\n\nYour lesson is confirmed. Please arrive 5 minutes early.\nIf you need to make any changes, please contact us as soon as possible.", "Safe driving,\nThe Driving School Team", null, [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
                'has_fees' => 'This payment includes a booking fee, digital fee or guarantee',
            ]),

            self::entry(EmailTemplateKey::LearnerPaidInFullPaymentReceived, 'Payment confirmed (paid in full)', $audience, 'Sent when a learner pays for a whole package in full.', [
                'recipient_name' => 'Greeting name (learner, or the contact who booked)',
                'learner_name' => 'Learner full name',
                'learner_first_name' => 'Learner first name',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'lesson_cost' => 'Lessons cost, excluding fees',
                'booking_fee' => 'Booking fee (blank when none)',
                'digital_fee' => 'Digital fee (blank when none)',
                'guarantee_fee' => 'Pass Your Test Guarantee charge (blank when none or free)',
                'total' => 'Total paid',
                'lessons_list' => 'Every booked lesson with its date and time, one per line',
            ], 'Payment Confirmed: Your {{lessons_count}} Driving Lessons Are Paid in Full', 'Hello {{recipient_name}}!', "We've received your payment of **{{total}}** for {{#if booked_by_contact}}**{{learner_name}}'s**{{else}}your{{/if}} driving lessons. They are paid in full.\n\n**Booking Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n\n**Payment — paid in full:**\n".self::ORDER_COST_LINES."\n**Total paid: {{total}}**\n\n**Your lessons:**\n{{lessons_list}}\n\nThere is nothing more to pay for these lessons. Please arrive 5 minutes early.\nIf you need to make any changes, please contact us as soon as possible.", "Safe driving,\nThe Driving School Team", null, [
                'booked_by_contact' => 'A contact (e.g. parent) booked on behalf of the learner',
                'guarantee_included_free' => 'The Pass Your Test Guarantee is included free',
            ]),

            self::entry(EmailTemplateKey::LearnerInstructorOnWay, 'Instructor on the way', $audience, 'Sent when the instructor taps “on my way” for a lesson.', [
                'recipient_name' => 'Booker first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date, e.g. "Monday, 1 June" (blank if unknown)',
                'lesson_time' => 'Lesson start time, e.g. "14:00" (blank if unknown)',
                'app_name' => 'Application name',
            ], 'Your instructor is on the way', 'Hello {{recipient_name}},', "**{{instructor_name}}** is on their way to your driving lesson{{#if lesson_date}} on {{lesson_date}}{{#if lesson_time}} at {{lesson_time}}{{/if}}{{/if}}.\n\nPlease be ready at your agreed pickup point.", "See you soon,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerInstructorArrived, 'Instructor arrived', $audience, 'Sent when the instructor taps “I’ve arrived” for a lesson.', [
                'recipient_name' => 'Booker first name',
                'instructor_name' => 'Instructor name',
                'app_name' => 'Application name',
            ], 'Your instructor has arrived', 'Hello {{recipient_name}},', "**{{instructor_name}}** has arrived for your driving lesson and is waiting for you at the pickup point.\n\nPlease head out when you are ready.", "Have a great lesson,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerStudentTransfer, 'Transferred to a new instructor', $audience, 'Sent to the learner when they are moved to another instructor.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'New instructor name',
                'app_name' => 'Application name',
            ], 'Your driving lessons have moved to a new instructor', 'Hello {{recipient_name}},', "Your driving lessons have been moved to **{{instructor_name}}**.\n\nAny future lessons you already had booked have been transferred into their diary at the same dates and times.\n\n{{instructor_name}} will be in touch shortly to introduce themselves.\n\nIf you have any questions, please reply to this email.", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerPasswordReset, 'Admin password reset (learner)', $audience, 'Sent when an admin resets a learner’s password from the CRM.', [
                'recipient_name' => 'Learner first name',
                'app_name' => 'Application name',
                'email' => 'Login email',
                'new_password' => 'New temporary password',
                'login_url' => 'Login URL',
            ], 'Your {{app_name}} password has been reset', 'Hello {{recipient_name}},', "An administrator has reset the password for your {{app_name}} account.\n\n**Email:** {{email}}\n**New password:** {{new_password}}\n\n{{action_button}}\n\nPlease sign in and change this password as soon as you can. If you did not expect this email, contact your instructor.", 'Thanks, {{app_name}}', 'Sign in'),

            self::entry(EmailTemplateKey::LearnerAccountDeletionRequested, 'Account deletion scheduled', $audience, 'Sent when a user requests account deletion, with the scheduled date.', [
                'recipient_name' => 'First name',
                'app_name' => 'Application name',
                'deletion_date' => 'Scheduled deletion date',
            ], 'Your {{app_name}} account is scheduled for deletion', 'Hello {{recipient_name}},', "We've received a request to delete your {{app_name}} account.\n\n**Your account is scheduled for deletion on:** {{deletion_date}}\n\nUntil then, nothing changes — you can keep using your account as normal. If you change your mind, open the app and cancel the request from Settings at any time before that date.\n\nAfter {{deletion_date}}, your account and personal data will be permanently deleted and cannot be recovered.\n\nIf you didn't request this, someone else may have access to your account. Sign in and cancel the deletion request from Settings straight away, then change your password.", null, null),

            self::entry(EmailTemplateKey::LearnerAccountDeletionCancelled, 'Account deletion cancelled', $audience, 'Sent when a pending account deletion request is cancelled.', [
                'recipient_name' => 'First name',
                'app_name' => 'Application name',
            ], 'Your {{app_name}} account deletion request has been cancelled', 'Hello {{recipient_name}},', "Your request to delete your {{app_name}} account has been cancelled. Your account will stay active and nothing will be removed.\n\nYou can keep using the app as normal.", null, null),

            self::entry(EmailTemplateKey::LearnerLessonResourcesAssigned, 'Resources assigned to a lesson', $audience, 'Sent when an instructor attaches resources to a lesson.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date',
                'resource_list' => 'List of resource titles and links',
            ], 'New resources assigned to your lesson', 'Hello {{recipient_name}},', "{{instructor_name}} has assigned resources for your lesson on **{{lesson_date}}**.\n\n{{resource_list}}\n\nThese links expire in 7 days, so open them before then.", null, null),

            self::entry(EmailTemplateKey::LearnerLessonResourceRecommendations, 'Recommended resources after a lesson', $audience, 'Sent after a lesson with recommended follow-up resources.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date',
                'summary_excerpt' => 'Short excerpt of the lesson summary',
                'resource_list' => 'List of resource titles and links',
            ], 'Recommended resources from your driving lesson', 'Hello {{recipient_name}},', "Following your lesson with {{instructor_name}} on **{{lesson_date}}**, here are some resources that may help.\n\n{{summary_excerpt}}\n\n{{resource_list}}\n\nThese links expire in 7 days, so open them before then.", null, null),

            self::entry(EmailTemplateKey::LearnerLessonFeedbackRequest, 'Lesson feedback request', $audience, 'Sent after a lesson asking the learner how it went.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time range, e.g. "10:00 - 11:00" (blank if not set)',
            ], 'How was your driving lesson?', 'Hello {{recipient_name}},', "How was your driving lesson with **{{instructor_name}}** on **{{lesson_date}}**{{#if lesson_time}} ({{lesson_time}}){{/if}}?\n\nYour feedback helps us keep lessons useful and enjoyable.", null, null),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }>
     */
    private static function instructorDefinitions(): array
    {
        $audience = EmailTemplateAudience::Instructor->value;

        return [
            self::entry(EmailTemplateKey::InstructorWelcome, 'Instructor welcome / password setup', $audience, 'Sent when an admin creates an instructor, with a link to set their password.', [
                'recipient_name' => 'Instructor first name',
                'app_name' => 'Application name',
                'email' => 'Sign-in email',
                'setup_url' => 'Password setup URL',
                'expires_in_minutes' => 'Link expiry in minutes',
                'login_url' => 'Login URL',
            ], 'Welcome to {{app_name}} — set up your instructor account', 'Welcome to {{app_name}}, {{recipient_name}}', "An administrator has just created an instructor account for you on {{app_name}}. To start managing your pupils, calendar and payouts, you'll need to set a password and sign in.\n\n**Here's what to do next:**\n1. Click the **Set up your account** button below.\n2. Choose a strong password on the page that opens.\n3. Sign in with your email and the password you just created.\n\n**Your sign-in email:** {{email}}\n\n{{action_button}}\n\nIf the button doesn't work, copy and paste this link into your browser:\n{{setup_url}}\n\nFor your security, this setup link will expire in 24 hours. If it expires before you've finished, just visit {{login_url}} and choose **Forgot password** — we'll send a fresh link.\n\nDidn't expect this email? You can safely ignore it — no account is active until the link above is used.", null, 'Set up your account'),

            self::entry(EmailTemplateKey::InstructorStripeSetupLink, 'Stripe setup link', $audience, 'Sent when an admin emails an instructor a link to connect their Stripe account so they can be paid.', [
                'recipient_name' => 'Instructor first name',
                'app_name' => 'Application name',
                'setup_url' => 'Stripe setup URL',
                'expires_in_days' => 'Link expiry in days',
            ], 'Connect Stripe to get paid on {{app_name}}', 'Hello {{recipient_name}},', "To receive payments for your lessons on {{app_name}}, you need to connect a Stripe account. It only takes a few minutes.\n\n**Here's what to do:**\n1. Click the **Connect Stripe** button below.\n2. Follow Stripe's steps to add your details and bank account.\n3. When you see **You're all sorted**, open the {{app_name}} app. It will show you as connected.\n\n{{action_button}}\n\nIf the button doesn't work, copy and paste this link into your browser:\n{{setup_url}}\n\nThis link expires in {{expires_in_days}} days. If it expires, ask us to send you a new one.", 'Thanks, {{app_name}}', 'Connect Stripe'),

            self::entry(EmailTemplateKey::InstructorStudentAssigned, 'New student assigned by admin', $audience, 'Sent when an admin assigns a learner to an instructor.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'app_name' => 'Application name',
            ], 'New student assigned: {{student_name}}', 'Hello {{recipient_name}},', "A new student, **{{student_name}}**, has been assigned to you by an administrator.\n\nLog in to view their details and get in touch to arrange their first lesson.\n\n{{action_button}}", "Thanks,\nThe {{app_name}} Team", 'View student'),

            self::entry(EmailTemplateKey::InstructorStudentGained, 'Student transferred in', $audience, 'Sent to the destination instructor when a learner is transferred to them.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'source_name' => 'Previous instructor name',
                'moved_lessons_count' => 'Number of future lessons added to your diary',
                'lesson_list' => 'List of transferred lessons (clashes are flagged)',
                'clash_count' => 'Number of transferred lessons that clash with your diary',
                'app_name' => 'Application name',
            ], 'New student assigned: {{student_name}}', 'Hello {{recipient_name}},', "**{{student_name}}** has been transferred to you from **{{source_name}}**.\n\n{{#if moved_lessons_count}}{{#if multiple_lessons}}The following {{moved_lessons_count}} lessons have been added to your diary at their existing dates and times:{{else}}The following lesson has been added to your diary at its existing date and time:{{/if}}\n{{lesson_list}}{{#if clash_count}}\n\n⚠️ **{{clash_count}} {{#if multiple_clashes}}clashes{{else}}clash{{/if}} flagged above.** Please review your diary and rebook the affected lessons at alternative times that suit you and the student.{{/if}}{{else}}No future lessons were on the previous instructor’s diary, so nothing has been added to yours yet. The student will book new lessons with you as normal.{{/if}}\n\nPayment for any future lessons will be sent to your Stripe account once the lesson has been signed off.", "Thanks,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one lesson was added to your diary',
                'multiple_clashes' => 'More than one transferred lesson clashes',
            ]),

            self::entry(EmailTemplateKey::InstructorStudentLost, 'Student transferred away', $audience, 'Sent to the previous instructor when a learner is transferred away.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'destination_name' => 'New instructor name',
                'lessons_removed' => 'Number of future lessons removed from your diary',
                'app_name' => 'Application name',
            ], 'Student transferred: {{student_name}}', 'Hello {{recipient_name}},', "**{{student_name}}** has been transferred to **{{destination_name}}**.\n\n{{#if lessons_removed}}{{#if multiple_lessons}}{{lessons_removed}} future lessons have been removed from your diary.{{else}}1 future lesson has been removed from your diary.{{/if}}{{else}}No future lessons were on your diary at the time of transfer.{{/if}}\n\nAny lessons you have already taught and been paid for remain attached to you — this transfer only affects future bookings.", "Thanks,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one future lesson was removed',
            ]),

            self::entry(EmailTemplateKey::InstructorLessonsBulkRescheduled, 'Lessons bulk-rescheduled (instructor)', $audience, 'Confirmation to the instructor after they move a run of upcoming lessons.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'total_lessons' => 'How many lessons moved',
                'day_of_week' => 'New weekday',
                'time' => 'New time range',
                'start_date' => 'First new lesson date',
                'app_name' => 'Application name',
            ], 'Lessons rescheduled for {{student_name}}', 'Hello {{recipient_name}},', "You have rescheduled **{{total_lessons}}** upcoming {{#if multiple_lessons}}lessons{{else}}lesson{{/if}} for **{{student_name}}**.\n\nNew schedule: **{{day_of_week}}s at {{time}}**, starting **{{start_date}}**.\n\nThe student has been notified by email.", "Thanks,\nThe {{app_name}} Team", null, [
                'multiple_lessons' => 'More than one lesson was moved',
            ]),

            self::entry(EmailTemplateKey::InstructorLessonPaymentReceived, 'Learner paid for a lesson', $audience, 'Sent to the instructor when a learner pays for an upcoming lesson. The amount is the lesson fee the instructor receives, excluding the booking fee and digital fee.', [
                'student_name' => 'Learner name',
                'amount' => 'Lesson fee the instructor receives',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
            ], 'Payment Received: {{student_name}} — Lesson on {{lesson_date}}', 'Hello!', "**{{student_name}}** has paid for their upcoming lesson.\n\n**Lesson Details:**\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nLesson fee: {{amount}}\n\nThis lesson is now confirmed and paid.", "Best regards,\nThe Driving School Team", null),

            self::entry(EmailTemplateKey::InstructorPaidInFullPaymentReceived, 'Learner paid in full for a package', $audience, 'Sent to the instructor when a learner pays for a whole package in full. The amounts are the lesson fees the instructor receives, excluding the booking fee and digital fee.', [
                'student_name' => 'Learner name',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'lesson_fee' => 'Lesson fee the instructor receives for each lesson',
                'amount' => 'Lesson fees the instructor receives for the whole package',
                'lessons_list' => 'Every booked lesson with its date and time, one per line',
            ], 'Payment Received: {{student_name}} — {{lessons_count}} Lessons Paid in Full', 'Hello!', "**{{student_name}}** has paid in full for {{lessons_count}} lessons.\n\n**Booking Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nLesson fee: {{lesson_fee}} per lesson\nTotal lesson fees: {{amount}}\n\n**Lessons:**\n{{lessons_list}}\n\nThese lessons are now confirmed and paid. You receive each lesson fee when you sign that lesson off.", "Best regards,\nThe Driving School Team", null),

            self::entry(EmailTemplateKey::InstructorLessonSignedOff, 'Lesson signed off (instructor)', $audience, 'Sent to the instructor after they sign off a lesson.', [
                'recipient_name' => 'Instructor name',
                'student_name' => 'Learner name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time range (blank if not set)',
                'app_name' => 'Application name',
            ], 'Lesson Signed Off — {{student_name}}', 'Hello {{recipient_name}}!', "You have signed off the lesson with **{{student_name}}** on **{{lesson_date}}**.\n{{#if lesson_time}}Lesson time: {{lesson_time}}{{/if}}\n{{#if payout_initiated}}The payout for this lesson has been initiated to your account.{{/if}}", "Thanks,\nThe {{app_name}} Team", null, [
                'payout_initiated' => 'The payout transfer was started (false when it is held for a platform balance shortfall)',
            ]),

            self::entry(EmailTemplateKey::InstructorCalendarClash, 'Scheduling clash detected', $audience, 'Sent when a new calendar item overlaps existing items.', [
                'recipient_name' => 'Instructor name',
                'date' => 'Clash date',
                'new_item' => 'New item time range',
                'clash_list' => 'List of overlapping items',
                'app_name' => 'Application name',
            ], 'Scheduling Clash Detected — {{date}}', 'Hello {{recipient_name}}!', "A scheduling clash has been detected on your calendar for **{{date}}**.\n\n**New item:** {{new_item}}\n\n{{clash_list}}\n\nPlease review your calendar and reschedule any affected lessons.\n\n{{action_button}}", "Thanks,\nThe {{app_name}} Team", 'View Calendar'),

            self::entry(EmailTemplateKey::InstructorVatDueSoon, 'MTD VAT return due', $audience, 'Reminder that an HMRC VAT obligation is due soon.', [
                'recipient_name' => 'Instructor name',
                'when' => '"today", "tomorrow", or "in N days"',
                'period' => 'VAT period dates',
            ], 'MTD VAT return due {{when}}', 'Hello {{recipient_name}},', "Your VAT return for {{period}} is due {{when}}.\n\n{{action_button}}\n\nVAT submissions are final once filed — corrections must be made in a future period.", null, 'Open VAT submissions'),

            self::entry(EmailTemplateKey::InstructorItsaDueSoon, 'MTD ITSA quarterly update due', $audience, 'Reminder that an HMRC ITSA quarterly update is due soon.', [
                'recipient_name' => 'Instructor name',
                'when' => '"today", "tomorrow", or "in N days"',
                'period' => 'ITSA period dates',
            ], 'MTD ITSA quarterly update due {{when}}', 'Hello {{recipient_name}},', "Your quarterly self-employment update for {{period}} is due {{when}}.\n\n{{action_button}}\n\nFiling on time keeps you compliant with HMRC and avoids automatic penalties.", null, 'Open ITSA submissions'),

            self::entry(EmailTemplateKey::InstructorHmrcReconnect, 'HMRC connection expiring', $audience, 'Reminder that the instructor’s HMRC connection needs renewing.', [
                'recipient_name' => 'Instructor name',
                'when' => '"tomorrow" or "in N days"',
                'app_name' => 'Application name',
            ], 'HMRC connection needs reconnecting {{when}}', 'Hello {{recipient_name}},', "Your HMRC connection will need to be renewed {{when}}.\n\nYou can keep filing as usual until then. After that, you will need to sign in to HMRC again so we can keep submitting on your behalf.\n\n{{action_button}}\n\nThank you for using {{app_name}}.", null, 'Renew HMRC connection'),

            self::entry(EmailTemplateKey::InstructorYearEndArchiveReady, 'Tax-year archive ready', $audience, 'Sent when a year-end tax archive ZIP is ready to download.', [
                'recipient_name' => 'Instructor name',
                'tax_year_label' => 'Tax year label',
                'file_size' => 'File size, e.g. "12.34 MB" (blank if unknown)',
                'finance_rows' => 'Finance row count',
                'mileage_rows' => 'Mileage row count',
                'receipts' => 'Receipt count',
                'submissions' => 'Submission count',
                'link_expires_at' => 'Download link expiry',
                'retention_years' => 'How long the archive is kept',
            ], 'Your {{tax_year_label}} tax-year archive is ready', 'Hello {{recipient_name}},', "Your {{tax_year_label}} tax-year archive is ready to download.\n\nIt includes {{finance_rows}} finance rows, {{mileage_rows}} mileage logs, {{receipts}} receipts and {{submissions}} submissions{{#if file_size}} ({{file_size}}){{/if}}.\n\n{{action_button}}\n\nThis download link expires on {{link_expires_at}}. We keep a copy for {{retention_years}} years to meet HMRC record-keeping rules.", null, 'Download archive'),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }>
     */
    private static function sharedDefinitions(): array
    {
        return [
            self::entry(EmailTemplateKey::SharedNewMessage, 'New in-app message', EmailTemplateAudience::Both->value, 'Sent to an instructor or learner when they receive a new in-app message.', [
                'recipient_name' => 'Recipient name',
                'sender_name' => 'Sender name',
                'message_excerpt' => 'Short excerpt of the message',
            ], 'New Message from {{sender_name}}', 'Hello {{recipient_name}}!', "{{sender_name}} has sent you a new message:\n\n\"{{message_excerpt}}\"\n\n{{action_button}}\n\nThank you for using our platform!", null, 'View Messages'),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }>
     */
    private static function adminDefinitions(): array
    {
        return [
            self::entry(EmailTemplateKey::AdminWelcome, 'Admin welcome / login details', EmailTemplateAudience::Admin->value, 'Sent when an owner adds a new admin from Owner Access, with their temporary password.', [
                'recipient_name' => 'Admin first name',
                'app_name' => 'Application name',
                'email' => 'Login email',
                'temporary_password' => 'Temporary password',
                'access_level' => 'Admin access level (All access / Restricted)',
                'login_url' => 'Login URL',
            ], 'Your {{app_name}} admin account', 'Hello {{recipient_name}},', "An admin account has been created for you on {{app_name}}.\n\n**Email:** {{email}}\n**Temporary password:** {{temporary_password}}\n**Access level:** {{access_level}}\n\n{{action_button}}\n\nPlease sign in and change this password straight away from Settings → Password.", 'Thanks, {{app_name}}', 'Sign in'),
        ];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<string, string>  $conditions
     * @return array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     conditions: array<string, string>,
     *     subject: string,
     *     greeting: string,
     *     body: string,
     *     salutation: string,
     *     action_text: ?string
     * }
     */
    private static function entry(
        EmailTemplateKey $key,
        string $name,
        string $audience,
        string $description,
        array $placeholders,
        string $subject,
        string $greeting,
        string $body,
        ?string $salutation,
        ?string $actionText,
        array $conditions = [],
    ): array {
        return [
            'key' => $key->value,
            'name' => $name,
            'audience' => $audience,
            'description' => $description,
            'placeholders' => $placeholders,
            'conditions' => $conditions,
            'subject' => $subject,
            'greeting' => $greeting,
            'body' => $body,
            'salutation' => $salutation ?? '',
            'action_text' => $actionText,
        ];
    }
}
