<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_PAYMENT_PARAGRAPH = '{{#if refund_due}}{{#if late_cancellation_charged}}Some of these lessons were cancelled less than {{late_notice_hours}} hours before they were due to start, so what you paid for those is not refundable. You will be refunded what you paid for the others, including the digital fee and any Pass Your Test Guarantee. The booking fee is non-refundable. Our head office will be in touch about this shortly.{{else}}You will be refunded what you paid for these lessons, including the digital fee and any Pass Your Test Guarantee. The booking fee is non-refundable. Our head office will be in touch about this shortly.{{/if}}{{else}}{{#if late_cancellation_charged}}This was cancelled less than {{late_notice_hours}} hours before the lesson was due to start, so what you paid is not refundable.{{else}}There is nothing further you need to do, and you will not be charged for these lessons.{{/if}}{{/if}}';

    private const NEW_PAYMENT_PARAGRAPH = '{{#if refund_due}}Our head office will be in touch shortly about what you paid for {{#if multiple_lessons}}these lessons{{else}}this lesson{{/if}}.{{else}}There is nothing further you need to do, and you will not be charged for these lessons.{{/if}}';

    /**
     * Cancelled paid lessons are no longer refunded or kept automatically:
     * Head Office reviews each one. The learner cancellation email stops
     * promising a refund or saying the lesson is not refundable. Only the
     * default paragraph is swapped; a staff-edited body is left alone and
     * logged so it can be updated by hand.
     */
    public function up(): void
    {
        $this->swap(self::OLD_PAYMENT_PARAGRAPH, self::NEW_PAYMENT_PARAGRAPH);
    }

    public function down(): void
    {
        $this->swap(self::NEW_PAYMENT_PARAGRAPH, self::OLD_PAYMENT_PARAGRAPH);
    }

    private function swap(string $from, string $to): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $template = DB::table('email_templates')->where('key', 'learner.booking_cancelled')->first();

        if (! $template) {
            return;
        }

        if (! str_contains((string) $template->body, $from)) {
            Log::warning('learner.booking_cancelled email has been edited — update its refund wording by hand.');

            return;
        }

        DB::table('email_templates')
            ->where('key', 'learner.booking_cancelled')
            ->update([
                'body' => str_replace($from, $to, (string) $template->body),
                'updated_at' => now(),
            ]);
    }
};
