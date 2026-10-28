<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Mail\EmailTemplateInterpolator;
use App\Mail\RenderedEmailTemplate;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;

class PreviewEmailTemplateAction
{
    /**
     * Templates sent as Mailables with the branded `emails.templated` view.
     * Every other template is sent as a notification using Laravel's mail layout.
     */
    private const MAILABLE_TEMPLATES = [
        EmailTemplateKey::AdminWelcome,
        EmailTemplateKey::InstructorStripeSetupLink,
        EmailTemplateKey::InstructorWelcome,
        EmailTemplateKey::InstructorYearEndArchiveReady,
        EmailTemplateKey::LearnerAccountDeletionCancelled,
        EmailTemplateKey::LearnerAccountDeletionRequested,
        EmailTemplateKey::LearnerLessonFeedbackRequest,
        EmailTemplateKey::LearnerLessonResourceRecommendations,
        EmailTemplateKey::LearnerLessonResourcesAssigned,
        EmailTemplateKey::LearnerPasswordReset,
    ];

    /**
     * Example values used in place of real data when previewing.
     */
    private const SAMPLE_VALUES = [
        'recipient_name' => 'Alex',
        'instructor_name' => 'Sam Taylor',
        'email' => 'alex.morgan@example.com',
        'temporary_password' => 'Xk7-pQ2m',
        'new_password' => 'Rt4-wN8z',
        'learner_name' => 'Alex Morgan',
        'learner_first_name' => 'Alex',
        'student_name' => 'Alex Morgan',
        'package_name' => '10 Hour Package',
        'lessons_count' => 5,
        'lesson_count' => 3,
        'total_lessons' => 4,
        'moved_lessons_count' => 4,
        'lessons_removed' => 4,
        'clash_count' => 1,
        'first_lesson_date' => 'Monday, 2 November 2026',
        'lesson_cost' => '£350.00',
        'booking_fee' => '£19.99',
        'digital_fee' => '£4.99',
        'guarantee_fee' => '£49.00',
        'total' => '£423.98',
        'weekly_instalment' => '£74.99',
        'lesson_price' => '£35.00',
        'lesson_fee' => '£31.50',
        'amount' => '£315.00',
        'pay_by' => 'Friday, 30 October 2026',
        'weekly_payment_due_hours' => 48,
        'lesson_date' => 'Monday, 2 November 2026',
        'lesson_time' => '10:00 – 12:00',
        'lesson_list' => "Monday, 2 November 2026, 10:00 – 12:00\nMonday, 9 November 2026, 10:00 – 12:00 (clash)",
        'lessons_list' => "Monday, 2 November 2026, 10:00 – 12:00\nMonday, 9 November 2026, 10:00 – 12:00\nMonday, 16 November 2026, 10:00 – 12:00",
        'reason' => 'Instructor unwell',
        'instructor_notes' => 'Great progress on roundabouts today. Next time we will focus on parallel parking.',
        'summary_excerpt' => 'Great progress on roundabouts today. Next time we will focus on parallel parking.',
        'notes' => 'Same pick-up point as usual.',
        'start_date' => 'Tuesday, 3 November 2026',
        'day_of_week' => 'Tuesday',
        'time' => '14:00 – 16:00',
        'old_when' => 'Monday, 2 November 2026 at 10:00 – 12:00',
        'old_date' => 'Monday, 2 November 2026',
        'old_time' => '10:00 – 12:00',
        'new_when' => 'Wednesday, 4 November 2026 at 14:00 – 16:00',
        'new_date' => 'Wednesday, 4 November 2026',
        'new_time' => '14:00 – 16:00',
        'new_item' => '14:00 – 16:00',
        'date' => 'Wednesday, 4 November 2026',
        'clash_list' => 'Lesson with Jordan Lee, 13:00 – 15:00',
        'rebook_by' => '28 October 2027',
        'expiry_months' => 12,
        'deletion_date' => '27 November 2026',
        'resource_list' => "- Roundabouts explained\n- Parallel parking step by step",
        'expires_in_minutes' => 60,
        'expires_in_days' => 7,
        'source_name' => 'Chris Patel',
        'destination_name' => 'Sam Taylor',
        'when' => 'in 7 days',
        'period' => '6 July 2026 – 5 October 2026',
        'tax_year_label' => '2025-26',
        'file_size' => '12.34 MB',
        'finance_rows' => 214,
        'mileage_rows' => 96,
        'receipts' => 58,
        'submissions' => 4,
        'link_expires_at' => '4 November 2026',
        'retention_years' => 6,
        'sender_name' => 'Sam Taylor',
        'message_excerpt' => 'Hi Alex, can we move Monday\'s lesson to the afternoon?',
        'access_level' => 'All access',
    ];

    public function __construct(
        protected EmailTemplateInterpolator $interpolator,
        protected BuildTemplatedMailMessageAction $buildTemplatedMailMessage,
    ) {}

    /**
     * Render the email as recipients would see it, filled with example values.
     * Copy passed in (unsaved edits) wins over the saved copy.
     *
     * @param  array{subject?: ?string, greeting?: ?string, body?: ?string, salutation?: ?string, action_text?: ?string}  $copy
     * @param  list<string>  $enabledConditions
     * @return array{subject: string, html: string}
     */
    public function __invoke(EmailTemplateKey $key, array $copy = [], array $enabledConditions = []): array
    {
        $definition = EmailTemplateCatalog::definition($key);
        $stored = EmailTemplate::query()->where('key', $key->value)->first();

        $replacements = $this->replacements($definition, $enabledConditions);

        $source = fn (string $field): string => (string) (array_key_exists($field, $copy)
            ? $copy[$field]
            : ($stored?->{$field} ?? $definition[$field]));

        $actionText = trim($this->interpolator->interpolate($source('action_text'), $replacements));

        $rendered = new RenderedEmailTemplate(
            subject: $this->interpolator->interpolate($source('subject'), $replacements),
            greeting: $this->interpolator->interpolate($source('greeting'), $replacements),
            body: $this->interpolator->interpolate($source('body'), $replacements),
            salutation: $this->interpolator->interpolate($source('salutation'), $replacements),
            actionText: $actionText === '' ? null : $actionText,
            actionUrl: config('app.url'),
        );

        $html = in_array($key, self::MAILABLE_TEMPLATES, true)
            ? view('emails.templated', app(EmailTemplateService::class)->viewData($rendered))->render()
            : (string) ($this->buildTemplatedMailMessage)($rendered)->render();

        return [
            'subject' => $rendered->subject,
            'html' => $html,
        ];
    }

    /**
     * @param  array{placeholders: array<string, string>, conditions: array<string, string>}  $definition
     * @param  list<string>  $enabledConditions
     * @return array<string, scalar|null>
     */
    private function replacements(array $definition, array $enabledConditions): array
    {
        $replacements = ['app_name' => config('app.name'), 'login_url' => config('app.url'), 'setup_url' => config('app.url')];

        foreach (array_keys($definition['placeholders']) as $placeholder) {
            $replacements[$placeholder] ??= self::SAMPLE_VALUES[$placeholder]
                ?? '['.$definition['placeholders'][$placeholder].']';
        }

        foreach (array_keys($definition['conditions']) as $condition) {
            $replacements[$condition] = in_array($condition, $enabledConditions, true);
        }

        if (in_array('guarantee_included_free', $enabledConditions, true)) {
            $replacements['guarantee_fee'] = null;
        }

        return $replacements;
    }
}
