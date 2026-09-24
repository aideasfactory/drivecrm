<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', 'instructor.welcome')
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace(
                'expire in {{expires_in_minutes}} minutes',
                'expire in 24 hours',
                (string) $template->body,
            );

            if ($body === $template->body) {
                continue;
            }

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update(['body' => $body]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', 'instructor.welcome')
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace(
                'expire in 24 hours',
                'expire in {{expires_in_minutes}} minutes',
                (string) $template->body,
            );

            if ($body === $template->body) {
                continue;
            }

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update(['body' => $body]);
        }
    }
};
