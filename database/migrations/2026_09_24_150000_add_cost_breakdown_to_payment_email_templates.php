<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored template bodies override the catalog defaults, so the new
     * {{cost_breakdown}} placeholder is only inserted where the surrounding
     * default copy is still intact. Staff-edited copy is left untouched.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $replacements = [
        'learner.payment_link' => [
            "Instructor: {{instructor_name}}\nTotal: {{total}}",
            "Instructor: {{instructor_name}}\n{{cost_breakdown}}\nTotal: {{total}}",
        ],
        'learner.payment_due_soon' => [
            "Time: {{lesson_time}}\nAmount due: {{amount}}",
            "Time: {{lesson_time}}\n{{cost_breakdown}}\nAmount due: {{amount}}",
        ],
        'learner.lesson_payment_received' => [
            "Instructor: {{instructor_name}}\nAmount paid: {{amount}}",
            "Instructor: {{instructor_name}}\n{{cost_breakdown}}\nAmount paid: {{amount}}",
        ],
    ];

    public function up(): void
    {
        foreach ($this->replacements as $key => [$from, $to]) {
            $this->replaceInBody($key, $from, $to);
        }
    }

    public function down(): void
    {
        foreach ($this->replacements as $key => [$from, $to]) {
            $this->replaceInBody($key, $to, $from);
        }
    }

    private function replaceInBody(string $key, string $search, string $replace): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', $key)
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace($search, $replace, (string) $template->body);

            if ($body === $template->body) {
                continue;
            }

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update(['body' => $body]);
        }
    }
};
