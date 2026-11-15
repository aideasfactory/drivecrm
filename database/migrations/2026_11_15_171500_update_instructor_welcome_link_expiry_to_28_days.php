<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Old copy => new copy, applied in order. The full default sentence goes
     * first; the shorter phrases catch staff-edited copy that kept the old
     * wording (`{{expires_in_minutes}}` is no longer filled and would render blank).
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        'For your security, this setup link will expire in 24 hours.' => 'This setup link expires on {{link_expires_at}} (28 days after this email was sent).',
        'expire in 24 hours' => 'expire on {{link_expires_at}}',
        'expire in {{expires_in_minutes}} minutes' => 'expire on {{link_expires_at}}',
    ];

    public function up(): void
    {
        $this->replaceCopy(self::REPLACEMENTS);
    }

    public function down(): void
    {
        $this->replaceCopy([
            'This setup link expires on {{link_expires_at}} (28 days after this email was sent).' => 'For your security, this setup link will expire in 24 hours.',
            'expire on {{link_expires_at}}' => 'expire in 24 hours',
        ]);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceCopy(array $replacements): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', 'instructor.welcome')
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace(array_keys($replacements), array_values($replacements), (string) $template->body);

            if ($body === $template->body) {
                continue;
            }

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update(['body' => $body]);
        }
    }
};
