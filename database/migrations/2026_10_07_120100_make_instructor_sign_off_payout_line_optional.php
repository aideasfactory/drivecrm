<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored template bodies override the catalog. Swap the fixed payout
     * sentence for {{payout_line}} only where that default sentence is still
     * intact, so a sign-off can confirm without claiming a transfer was sent.
     */
    public function up(): void
    {
        $this->replaceInBody(
            'instructor.lesson_signed_off',
            'The payout for this lesson has been initiated to your account.',
            '{{payout_line}}',
        );
    }

    public function down(): void
    {
        $this->replaceInBody(
            'instructor.lesson_signed_off',
            '{{payout_line}}',
            'The payout for this lesson has been initiated to your account.',
        );
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
