<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIRST_LESSON_LINE = '{{#if first_lesson_date}}First lesson: {{first_lesson_date}}{{/if}}';

    private const LESSON_DETAILS_LINES = "{{#if lesson_details}}\n**Your lessons:**\n{{lesson_details}}{{else}}".self::FIRST_LESSON_LINE.'{{/if}}';

    /**
     * Templates whose "First lesson" line becomes the full {{lesson_details}}
     * list (date, start time, duration, instructor and pickup per lesson).
     *
     * @var list<string>
     */
    private array $keys = [
        'learner.payment_link',
        'learner.order_confirmation',
        'learner.cash_booking_confirmed',
    ];

    /**
     * Stored bodies override the catalog defaults, so the line is only swapped
     * where the default wording is still intact. Staff-edited copy is left
     * untouched.
     */
    public function up(): void
    {
        $this->replaceInBodies(self::FIRST_LESSON_LINE, self::LESSON_DETAILS_LINES, alreadyApplied: self::LESSON_DETAILS_LINES);
    }

    public function down(): void
    {
        $this->replaceInBodies(self::LESSON_DETAILS_LINES, self::FIRST_LESSON_LINE);
    }

    private function replaceInBodies(string $search, string $replace, ?string $alreadyApplied = null): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->whereIn('key', $this->keys)
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = (string) $template->body;

            if ($alreadyApplied !== null && str_contains($body, $alreadyApplied)) {
                continue;
            }

            $updated = str_replace($search, $replace, $body);

            if ($updated === $body) {
                continue;
            }

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update(['body' => $updated]);
        }
    }
};
