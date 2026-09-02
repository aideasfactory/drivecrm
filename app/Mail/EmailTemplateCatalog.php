<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateKey;
use InvalidArgumentException;

final class EmailTemplateCatalog
{
    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
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
                'recipient_name' => 'Greeting name (learner or contact)',
                'intro' => 'Opening sentence for this booking',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'first_lesson_line' => 'First lesson date, if booked',
                'payment_block' => 'Payment summary for this order',
                'booked_for_line' => 'Shown when a contact booked on behalf of a learner',
                'app_name' => 'Application name',
            ], 'Your Driving Lessons Have Been Booked!', 'Hello {{recipient_name}}!', "{{intro}}\n\n**Order Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{first_lesson_line}}\n\n{{payment_block}}\n\n**Next Steps:**\n1. Download the app to view your lesson schedule\n2. Your instructor will contact you to confirm the details\n3. Make sure to arrive 5 minutes early for your first lesson\n{{booked_for_line}}\n\n{{action_button}}\n\nThank you for choosing us for your driving lessons!", "Safe driving,\nThe Driving School Team", 'Download app'),

            self::entry(EmailTemplateKey::LearnerPaymentLink, 'Payment link for booked lessons', $audience, 'Sent when an instructor books lessons that still need paying.', [
                'recipient_name' => 'Greeting name (learner or contact)',
                'intro' => 'Opening sentence for this booking',
                'package_name' => 'Package name',
                'lessons_count' => 'Number of lessons',
                'instructor_name' => 'Instructor name',
                'total' => 'Formatted total',
                'first_lesson_line' => 'First lesson date, if booked',
                'booked_for_line' => 'Shown when a contact booked on behalf of a learner',
            ], 'Complete Your Payment for Driving Lessons', 'Hello {{recipient_name}}!', "{{intro}}\n\n**Booking Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\nTotal: {{total}}\n{{first_lesson_line}}\n\nPlease complete your payment using the link below to confirm your lessons.\n\n{{action_button}}\n\nThis payment link will expire after 24 hours.\n{{booked_for_line}}", "Safe driving,\nThe Driving School Team", 'Pay Now'),

            self::entry(EmailTemplateKey::LearnerPaymentDueSoon, 'Payment due soon (48 hours)', $audience, 'Reminder that a weekly lesson payment is due in under 48 hours.', [
                'recipient_name' => 'Greeting name (learner or contact)',
                'intro' => 'Opening reminder sentence',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'amount' => 'Amount due',
            ], 'Payment Due Soon: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "{{intro}}\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nAmount due: {{amount}}\n\nYour lesson is in less than 48 hours. Please complete your payment using the link below to secure it.\n\n{{action_button}}\n\nIf you have already paid, please disregard this email.", "Safe driving,\nThe Driving School Team", 'Pay Now'),

            self::entry(EmailTemplateKey::LearnerBookingCancelled, 'Booking cancelled', $audience, 'Sent to the learner when lessons are cancelled.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_word' => '"lesson has" or "lessons have"',
                'lesson_noun' => '"Lesson Has" or "Lessons Have"',
                'lesson_list' => 'Bulleted list of cancelled lessons',
                'reason' => 'Cancellation reason',
                'refund_line' => 'Refund or no-charge explanation',
                'app_name' => 'Application name',
            ], 'Your Driving {{lesson_noun}} Been Cancelled', 'Hello {{recipient_name}},', "We're letting you know that the following {{lesson_word}} been cancelled by **{{instructor_name}}**:\n\n{{lesson_list}}\n\n**Reason:**\n{{reason}}\n\n{{refund_line}}\n\nIf you have any questions, please get in touch with your instructor.", "Kind regards,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonSignedOff, 'Lesson signed off (learner)', $audience, 'Sent to the learner after their instructor signs off a lesson.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_date' => 'Lesson date',
                'lesson_time_line' => 'Lesson time, if set',
                'instructor_notes_block' => 'Instructor notes, if any',
                'app_name' => 'Application name',
            ], 'Your Driving Lesson Has Been Signed Off', 'Hello {{recipient_name}}!', "Great news! Your driving lesson on **{{lesson_date}}** has been signed off by {{instructor_name}}.\n{{lesson_time_line}}\n{{instructor_notes_block}}\n\nKeep up the great work on your driving journey!", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonsBulkRescheduled, 'Lessons bulk-rescheduled (learner)', $audience, 'Sent when an instructor moves a run of upcoming lessons to a new weekly slot.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'lesson_word' => '"lesson" or "lessons"',
                'start_date' => 'First new lesson date',
                'day_of_week' => 'New weekday',
                'time' => 'New time range',
                'total_lessons' => 'How many lessons moved',
                'app_name' => 'Application name',
            ], 'Your driving lessons have been rescheduled', 'Hello {{recipient_name}},', "Your upcoming {{lesson_word}} with **{{instructor_name}}** have been rescheduled.\n\nFrom **{{start_date}}**, you will now have your lessons on **{{day_of_week}}s at {{time}}**.\n\nTotal {{lesson_word}} moved: **{{total_lessons}}**.\n\nIf this new schedule does not work for you, please contact your instructor to arrange alternatives.", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonRescheduled, 'Single lesson rescheduled', $audience, 'Sent when one lesson is moved to a new date or time.', [
                'recipient_name' => 'Learner first name',
                'instructor_name' => 'Instructor name',
                'old_when' => 'Previous date and time',
                'new_when' => 'New date and time',
                'notes_block' => 'Optional notes from the instructor',
                'app_name' => 'Application name',
            ], 'Your Driving Lesson Has Been Rescheduled', 'Hello {{recipient_name}}!', "Your driving lesson with **{{instructor_name}}** has been rescheduled.\n\n**Previous:**\n{{old_when}}\n\n**New:**\n{{new_when}}\n{{notes_block}}\n\nIf this new time does not work for you, please contact your instructor to arrange an alternative.", "Safe driving,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::LearnerLessonPaymentReminder, 'Lesson payment required', $audience, 'Sent when a weekly lesson invoice is waiting to be paid.', [
                'recipient_name' => 'Greeting name (learner or contact)',
                'intro' => 'Opening reminder sentence',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'cost_breakdown' => 'Optional fee breakdown',
                'amount' => 'Amount due',
            ], 'Payment Required: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "{{intro}}\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\n{{cost_breakdown}}\n**Amount due: {{amount}}**\n\nPlease complete your payment using the link below to secure your lesson.\n\n{{action_button}}\n\nIf you have already paid, please disregard this email.", "Safe driving,\nThe Driving School Team", 'Pay Now'),

            self::entry(EmailTemplateKey::LearnerLessonPaymentReceived, 'Lesson payment confirmed', $audience, 'Sent when a weekly lesson payment succeeds.', [
                'recipient_name' => 'Greeting name (learner or contact)',
                'intro' => 'Payment confirmation sentence',
                'package_name' => 'Package name',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
                'instructor_name' => 'Instructor name',
                'amount' => 'Amount paid',
            ], 'Payment Confirmed: Your Driving Lesson on {{lesson_date}}', 'Hello {{recipient_name}}!', "{{intro}}\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nInstructor: {{instructor_name}}\nAmount paid: {{amount}}\n\nYour lesson is confirmed. Please arrive 5 minutes early.\nIf you need to make any changes, please contact us as soon as possible.", "Safe driving,\nThe Driving School Team", null),

            self::entry(EmailTemplateKey::LearnerInstructorOnWay, 'Instructor on the way', $audience, 'Sent when the instructor taps “on my way” for a lesson.', [
                'recipient_name' => 'Booker first name',
                'instructor_name' => 'Instructor name',
                'lesson_when' => 'Optional " on Monday, 1 June at 14:00" suffix',
                'app_name' => 'Application name',
            ], 'Your instructor is on the way', 'Hello {{recipient_name}},', "**{{instructor_name}}** is on their way to your driving lesson{{lesson_when}}.\n\nPlease be ready at your agreed pickup point.", "See you soon,\nThe {{app_name}} Team", null),

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
                'lesson_time' => 'Lesson time range, if set',
            ], 'How was your driving lesson?', 'Hello {{recipient_name}},', "How was your driving lesson with **{{instructor_name}}** on **{{lesson_date}}**{{lesson_time}}?\n\nYour feedback helps us keep lessons useful and enjoyable.", null, null),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
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
            ], 'Welcome to {{app_name}} — set up your instructor account', 'Welcome to {{app_name}}, {{recipient_name}}', "An administrator has just created an instructor account for you on {{app_name}}. To start managing your pupils, calendar and payouts, you'll need to set a password and sign in.\n\n**Here's what to do next:**\n1. Click the **Set up your account** button below.\n2. Choose a strong password on the page that opens.\n3. Sign in with your email and the password you just created.\n\n**Your sign-in email:** {{email}}\n\n{{action_button}}\n\nIf the button doesn't work, copy and paste this link into your browser:\n{{setup_url}}\n\nFor your security, this setup link will expire in {{expires_in_minutes}} minutes. If it expires before you've finished, just visit {{login_url}} and choose **Forgot password** — we'll send a fresh link.\n\nDidn't expect this email? You can safely ignore it — no account is active until the link above is used.", null, 'Set up your account'),

            self::entry(EmailTemplateKey::InstructorStudentAssigned, 'New student assigned by admin', $audience, 'Sent when an admin assigns a learner to an instructor.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'app_name' => 'Application name',
            ], 'New student assigned: {{student_name}}', 'Hello {{recipient_name}},', "A new student, **{{student_name}}**, has been assigned to you by an administrator.\n\nLog in to view their details and get in touch to arrange their first lesson.\n\n{{action_button}}", "Thanks,\nThe {{app_name}} Team", 'View student'),

            self::entry(EmailTemplateKey::InstructorStudentGained, 'Student transferred in', $audience, 'Sent to the destination instructor when a learner is transferred to them.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'source_name' => 'Previous instructor name',
                'lessons_block' => 'Transferred lesson list or empty-diary explanation',
                'app_name' => 'Application name',
            ], 'New student assigned: {{student_name}}', 'Hello {{recipient_name}},', "**{{student_name}}** has been transferred to you from **{{source_name}}**.\n\n{{lessons_block}}\n\nPayment for any future lessons will be sent to your Stripe account once the lesson has been signed off.", "Thanks,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::InstructorStudentLost, 'Student transferred away', $audience, 'Sent to the previous instructor when a learner is transferred away.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'destination_name' => 'New instructor name',
                'lessons_line' => 'How many future lessons were removed',
                'app_name' => 'Application name',
            ], 'Student transferred: {{student_name}}', 'Hello {{recipient_name}},', "**{{student_name}}** has been transferred to **{{destination_name}}**.\n\n{{lessons_line}}\n\nAny lessons you have already taught and been paid for remain attached to you — this transfer only affects future bookings.", "Thanks,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::InstructorLessonsBulkRescheduled, 'Lessons bulk-rescheduled (instructor)', $audience, 'Confirmation to the instructor after they move a run of upcoming lessons.', [
                'recipient_name' => 'Instructor first name',
                'student_name' => 'Learner name',
                'total_lessons' => 'How many lessons moved',
                'lesson_word' => '"lesson" or "lessons"',
                'day_of_week' => 'New weekday',
                'time' => 'New time range',
                'start_date' => 'First new lesson date',
                'app_name' => 'Application name',
            ], 'Lessons rescheduled for {{student_name}}', 'Hello {{recipient_name}},', "You have rescheduled **{{total_lessons}}** upcoming {{lesson_word}} for **{{student_name}}**.\n\nNew schedule: **{{day_of_week}}s at {{time}}**, starting **{{start_date}}**.\n\nThe student has been notified by email.", "Thanks,\nThe {{app_name}} Team", null),

            self::entry(EmailTemplateKey::InstructorLessonPaymentReceived, 'Learner paid for a lesson', $audience, 'Sent to the instructor when a learner pays for an upcoming lesson.', [
                'student_name' => 'Learner name',
                'amount' => 'Amount paid',
                'lesson_date' => 'Lesson date',
                'lesson_time' => 'Lesson time',
            ], 'Payment Received: {{student_name}} — Lesson on {{lesson_date}}', 'Hello!', "**{{student_name}}** has paid **{{amount}}** for their upcoming lesson.\n\n**Lesson Details:**\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nAmount: {{amount}}\n\nThis lesson is now confirmed and paid.", "Best regards,\nThe Driving School Team", null),

            self::entry(EmailTemplateKey::InstructorLessonSignedOff, 'Lesson signed off (instructor)', $audience, 'Sent to the instructor after they sign off a lesson and a payout is started.', [
                'recipient_name' => 'Instructor name',
                'student_name' => 'Learner name',
                'lesson_date' => 'Lesson date',
                'lesson_time_line' => 'Lesson time, if set',
                'app_name' => 'Application name',
            ], 'Lesson Signed Off — {{student_name}}', 'Hello {{recipient_name}}!', "You have signed off the lesson with **{{student_name}}** on **{{lesson_date}}**.\n{{lesson_time_line}}\nThe payout for this lesson has been initiated to your account.", "Thanks,\nThe {{app_name}} Team", null),

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
                'file_size' => 'File size, if known',
                'finance_rows' => 'Finance row count',
                'mileage_rows' => 'Mileage row count',
                'receipts' => 'Receipt count',
                'submissions' => 'Submission count',
                'link_expires_at' => 'Download link expiry',
                'retention_years' => 'How long the archive is kept',
            ], 'Your {{tax_year_label}} tax-year archive is ready', 'Hello {{recipient_name}},', "Your {{tax_year_label}} tax-year archive is ready to download.\n\nIt includes {{finance_rows}} finance rows, {{mileage_rows}} mileage logs, {{receipts}} receipts and {{submissions}} submissions{{file_size}}.\n\n{{action_button}}\n\nThis download link expires on {{link_expires_at}}. We keep a copy for {{retention_years}} years to meet HMRC record-keeping rules.", null, 'Download archive'),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
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
     * @param  array<string, string>  $placeholders
     * @return array{
     *     key: string,
     *     name: string,
     *     audience: string,
     *     description: string,
     *     placeholders: array<string, string>,
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
    ): array {
        return [
            'key' => $key->value,
            'name' => $name,
            'audience' => $audience,
            'description' => $description,
            'placeholders' => $placeholders,
            'subject' => $subject,
            'greeting' => $greeting,
            'body' => $body,
            'salutation' => $salutation ?? '',
            'action_text' => $actionText,
        ];
    }
}
