<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored template bodies override the catalog. Relabel the instructor
     * payment email so it no longer reads as the pupil's checkout total.
     * Staff copy that does not contain these phrases is left untouched; the
     * {{amount}} value is the lesson fee either way.
     *
     * @var array<string, string>
     */
    private array $replacements = [
        '**{{student_name}}** has paid **{{amount}}** for their upcoming lesson.' => '**{{student_name}}** has paid for their upcoming lesson.',
        'Amount: {{amount}}' => 'Lesson fee: {{amount}}',
    ];

    public function up(): void
    {
        $this->replaceInBody($this->replacements);
    }

    public function down(): void
    {
        $this->replaceInBody(array_flip($this->replacements));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceInBody(array $replacements): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->where('key', 'instructor.lesson_payment_received')
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace(
                array_keys($replacements),
                array_values($replacements),
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
