<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates whose cost breakdown labels fees "(weekly instalment)".
     *
     * @var list<string>
     */
    private array $keys = [
        'learner.payment_link',
        'learner.payment_due_soon',
        'learner.lesson_payment_reminder',
        'learner.lesson_payment_received',
    ];

    /**
     * Columns that can hold template copy.
     *
     * @var list<string>
     */
    private array $columns = ['subject', 'greeting', 'body', 'salutation', 'action_text'];

    /**
     * @var array<string, string>
     */
    private array $phrases = [
        'Booking fee (weekly instalment):' => 'Booking fee{{#if is_instalment}} (weekly instalment){{/if}}:',
        'Digital services fee (weekly instalment):' => 'Digital services fee{{#if is_instalment}} (weekly instalment){{/if}}:',
    ];

    /**
     * Only label the booking and digital fees as a weekly instalment when the
     * payment really is one ({{is_instalment}}), in default or staff-edited rows.
     */
    public function up(): void
    {
        $this->convert($this->phrases);
    }

    public function down(): void
    {
        $this->convert(array_flip($this->phrases));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function convert(array $replacements): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $templates = DB::table('email_templates')
            ->whereIn('key', $this->keys)
            ->get(['id', ...$this->columns]);

        foreach ($templates as $template) {
            $changes = [];

            foreach ($this->columns as $column) {
                $original = $template->{$column};

                if ($original === null) {
                    continue;
                }

                $converted = strtr((string) $original, $replacements);

                if ($converted !== $original) {
                    $changes[$column] = $converted;
                }
            }

            if ($changes !== []) {
                DB::table('email_templates')
                    ->where('id', $template->id)
                    ->update($changes);
            }
        }
    }
};
