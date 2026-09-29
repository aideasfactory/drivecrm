<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored template bodies override the catalog defaults, so the new
     * {{amount_label}} and {{pay_by_line}} placeholders are only inserted where
     * the surrounding default copy is still intact. Staff-edited copy is left
     * untouched.
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $replacements = [
        [
            "{{cost_breakdown}}\nTotal: {{total}}",
            "{{cost_breakdown}}\n{{amount_label}}: {{total}}",
        ],
        [
            "{{action_button}}\n\nThis payment link will expire after 24 hours.\n{{booked_for_line}}",
            "{{action_button}}\n\n{{pay_by_line}}\n{{booked_for_line}}",
        ],
    ];

    public function up(): void
    {
        foreach ($this->replacements as [$from, $to]) {
            $this->replaceInBody('learner.payment_link', $from, $to);
        }
    }

    public function down(): void
    {
        foreach ($this->replacements as [$from, $to]) {
            $this->replaceInBody('learner.payment_link', $to, $from);
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
