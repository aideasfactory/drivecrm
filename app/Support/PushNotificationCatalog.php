<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read-only list of the push notifications the system sends automatically.
 *
 * The copy mirrors the strings passed to PushNotificationService at each call
 * site. Keep this list in sync when a push is added, removed or reworded.
 */
final class PushNotificationCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     audience: 'learner'|'instructor'|'any',
     *     title: string,
     *     body: string,
     *     trigger: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'lesson_payment',
                'name' => 'Lesson payment required',
                'audience' => 'learner',
                'title' => 'Time to pay for your lesson',
                'body' => 'Check your email to pay for your upcoming lesson on {date}.',
                'trigger' => 'A weekly lesson invoice is created: when a weekly order is booked, and after the previous lesson is signed off.',
            ],
            [
                'key' => 'payment_due_48h',
                'name' => 'Payment reminder (48 hours)',
                'audience' => 'learner',
                'title' => 'Payment reminder',
                'body' => 'Your lesson payment is due — tap to pay',
                'trigger' => 'Once, 48 hours before a lesson that is still unpaid and already has an invoice.',
            ],
            [
                'key' => 'payment_link_resent',
                'name' => 'Payment link re-sent',
                'audience' => 'learner',
                'title' => 'Payment link re-sent',
                'body' => 'Your lesson payment link has been re-sent — tap to pay',
                'trigger' => 'The instructor re-sends a payment link from the app (not sent for staff bookings).',
            ],
            [
                'key' => 'instructor_on_way',
                'name' => 'Instructor on the way',
                'audience' => 'learner',
                'title' => 'Your instructor is on the way',
                'body' => '{instructor} is on their way to your driving lesson.',
                'trigger' => 'The instructor taps "On my way" for a lesson.',
            ],
            [
                'key' => 'instructor_arrived',
                'name' => 'Instructor arrived',
                'audience' => 'learner',
                'title' => 'Your instructor has arrived',
                'body' => '{instructor} has arrived for your driving lesson and is waiting for you.',
                'trigger' => 'The instructor taps "Arrived" for a lesson.',
            ],
            [
                'key' => 'slot_offer',
                'name' => 'Short notice lesson available',
                'audience' => 'learner',
                'title' => 'Short Notice Lesson Available',
                'body' => "The instructor's message, or: A short-notice lesson is available on {date} at {time}.",
                'trigger' => "The instructor offers a short-notice slot. Sent to all of the instructor's active pupils.",
            ],
            [
                'key' => 'miles_start',
                'name' => 'Mileage reminder (start)',
                'audience' => 'instructor',
                'title' => 'Mileage reminder',
                'body' => 'Remember to input your miles',
                'trigger' => 'Once, in the 30 minutes before a lesson starts.',
            ],
            [
                'key' => 'miles_end',
                'name' => 'Mileage reminder (end)',
                'audience' => 'instructor',
                'title' => 'Mileage reminder',
                'body' => 'Remember to input your miles',
                'trigger' => 'Once, 30 minutes after a lesson ends.',
            ],
            [
                'key' => 'hmrc_reconnect',
                'name' => 'HMRC connection expiring',
                'audience' => 'instructor',
                'title' => 'HMRC connection — action soon',
                'body' => 'Your HMRC connection will need renewing in {days} day(s). Tap to reconnect.',
                'trigger' => 'Daily check at 07:00. Sent 30 and 7 days before the HMRC connection expires.',
            ],
            [
                'key' => 'itsa_due_soon',
                'name' => 'MTD quarterly update due',
                'audience' => 'instructor',
                'title' => 'MTD ITSA — quarterly update due soon',
                'body' => 'Your quarterly update is due in {days} day(s). Tap to open.',
                'trigger' => 'Daily check at 07:15. Sent 30, 14, 7 and 1 days before the deadline.',
            ],
            [
                'key' => 'vat_due_soon',
                'name' => 'MTD VAT return due',
                'audience' => 'instructor',
                'title' => 'MTD VAT — return due soon',
                'body' => 'Your VAT return is due in {days} day(s). Tap to open.',
                'trigger' => 'Daily check at 07:15. Sent 30, 14, 7 and 1 days before the deadline.',
            ],
            [
                'key' => 'message',
                'name' => 'New message',
                'audience' => 'any',
                'title' => 'New message from {sender}',
                'body' => 'The message text (first 140 characters).',
                'trigger' => 'A direct message or broadcast is sent to the user.',
            ],
        ];
    }
}
