<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_COPY = 'For your security, this setup link will expire in 24 hours.';

    private const NEW_COPY = 'This setup link expires on {{link_expires_at}} (28 days after this email was sent).';

    public function up(): void
    {
        $this->replaceCopy(self::OLD_COPY, self::NEW_COPY);
    }

    public function down(): void
    {
        $this->replaceCopy(self::NEW_COPY, self::OLD_COPY);
    }

    private function replaceCopy(string $search, string $replace): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', 'instructor.welcome')
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
