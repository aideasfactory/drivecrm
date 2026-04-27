<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class SendBroadcastMessageAction
{
    public function __construct(
        protected SendMessagePushNotificationAction $sendMessagePushNotification,
    ) {}

    /**
     * Send a broadcast message to multiple recipients.
     *
     * @param  User  $sender  The user sending the message
     * @param  array<int>  $recipientUserIds  Array of recipient user IDs
     * @param  string  $message  The message content
     * @return Collection Collection of created Message models
     */
    public function __invoke(User $sender, array $recipientUserIds, string $message): Collection
    {
        $messages = collect();
        $recipients = User::whereIn('id', $recipientUserIds)->get()->keyBy('id');

        foreach ($recipientUserIds as $recipientUserId) {
            $createdMessage = Message::create([
                'from' => $sender->id,
                'to' => $recipientUserId,
                'message' => $message,
            ]);

            $messages->push($createdMessage);

            $recipient = $recipients->get($recipientUserId);

            if ($recipient) {
                ($this->sendMessagePushNotification)($createdMessage, $sender, $recipient);
            }
        }

        return $messages;
    }
}
