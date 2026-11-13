<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Shared\FindExcludedEmailRecipientsAction;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * The mail guard. Runs before every email leaves the app — mailables,
 * notifications sent by email, welcome emails, AI resource recommendations —
 * and removes recipients who must never be emailed (placeholder addresses
 * and users flagged `exclude_from_emails`). When nobody is left, the email
 * is not sent at all. Each drop is logged.
 */
class DropMailToExcludedRecipients
{
    protected const HEADERS = ['To', 'Cc', 'Bcc'];

    public function __construct(
        protected FindExcludedEmailRecipientsAction $findExcludedEmailRecipients,
    ) {}

    /**
     * @return false|null False stops the email from being sent
     */
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;

        $recipients = [
            'To' => $message->getTo(),
            'Cc' => $message->getCc(),
            'Bcc' => $message->getBcc(),
        ];

        $excluded = ($this->findExcludedEmailRecipients)(array_map(
            fn (Address $address) => $address->getAddress(),
            array_merge(...array_values($recipients)),
        ));

        if ($excluded === []) {
            return null;
        }

        $remaining = 0;

        foreach (self::HEADERS as $header) {
            $kept = array_values(array_filter(
                $recipients[$header],
                fn (Address $address) => ! in_array(strtolower($address->getAddress()), $excluded, true),
            ));

            $remaining += count($kept);

            $message->getHeaders()->remove($header);

            if ($kept !== []) {
                $message->getHeaders()->addMailboxListHeader($header, $kept);
            }
        }

        Log::info('Mail guard: dropped excluded recipients.', [
            'subject' => $message->getSubject(),
            'dropped' => $excluded,
            'sent_to_others' => $remaining > 0,
        ]);

        return $remaining > 0 ? null : false;
    }
}
