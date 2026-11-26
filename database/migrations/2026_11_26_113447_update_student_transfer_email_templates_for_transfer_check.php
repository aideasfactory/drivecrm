<?php

declare(strict_types=1);

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default bodies before the Pupil Transfer Check.
     *
     * @var array<string, string>
     */
    private const OLD_BODIES = [
        'learner.student_transfer' => "Your driving lessons have been moved to **{{instructor_name}}**.\n\nAny future lessons you already had booked have been transferred into their diary at the same dates and times.\n\n{{instructor_name}} will be in touch shortly to introduce themselves.\n\nIf you have any questions, please reply to this email.",
        'instructor.student_gained' => "**{{student_name}}** has been transferred to you from **{{source_name}}**.\n\n{{#if moved_lessons_count}}{{#if multiple_lessons}}The following {{moved_lessons_count}} lessons have been added to your diary at their existing dates and times:{{else}}The following lesson has been added to your diary at its existing date and time:{{/if}}\n{{lesson_list}}{{#if clash_count}}\n\n⚠️ **{{clash_count}} {{#if multiple_clashes}}clashes{{else}}clash{{/if}} flagged above.** Please review your diary and rebook the affected lessons at alternative times that suit you and the student.{{/if}}{{else}}No future lessons were on the previous instructor’s diary, so nothing has been added to yours yet. The student will book new lessons with you as normal.{{/if}}\n\nPayment for any future lessons will be sent to your Stripe account once the lesson has been signed off.",
        'instructor.student_lost' => "**{{student_name}}** has been transferred to **{{destination_name}}**.\n\n{{#if lessons_removed}}{{#if multiple_lessons}}{{lessons_removed}} future lessons have been removed from your diary.{{else}}1 future lesson has been removed from your diary.{{/if}}{{else}}No future lessons were on your diary at the time of transfer.{{/if}}\n\nAny lessons you have already taught and been paid for remain attached to you — this transfer only affects future bookings.",
    ];

    /**
     * The transfer emails now say which lessons are in the new diary, which
     * are unscheduled, which unpaid bookings were cancelled and which past
     * lessons were recorded as missed. Only bodies still on the old default
     * are replaced; a staff-edited body is left alone and logged so it can be
     * updated by hand (Settings → Email templates → Restore default).
     */
    public function up(): void
    {
        foreach (self::OLD_BODIES as $key => $oldBody) {
            $this->swap($key, $oldBody, EmailTemplateCatalog::definition(EmailTemplateKey::from($key))['body']);
        }
    }

    public function down(): void
    {
        foreach (self::OLD_BODIES as $key => $oldBody) {
            $this->swap($key, EmailTemplateCatalog::definition(EmailTemplateKey::from($key))['body'], $oldBody);
        }
    }

    private function swap(string $key, string $from, string $to): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $template = DB::table('email_templates')->where('key', $key)->first();

        if (! $template) {
            return;
        }

        if ((string) $template->body !== $from) {
            Log::warning("{$key} email has been edited — update its pupil transfer wording by hand.");

            return;
        }

        DB::table('email_templates')
            ->where('key', $key)
            ->update(['body' => $to, 'updated_at' => now()]);
    }
};
