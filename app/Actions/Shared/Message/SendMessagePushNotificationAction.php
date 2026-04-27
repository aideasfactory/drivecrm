<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Actions\PushNotification\QueuePushNotificationAction;
use App\Actions\PushNotification\SendPushNotificationAction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

class SendMessagePushNotificationAction
{
    public function __construct(
        protected QueuePushNotificationAction $queuePushNotification,
        protected SendPushNotificationAction $sendPushNotification,
    ) {}

    public function __invoke(Message $message, User $sender, User $recipient): void
    {
        if (! $recipient->expo_push_token) {
            return;
        }

        $title = 'New message from '.$sender->name;
        $body = Str::limit($message->message, 150);
        $data = [
            'type' => 'new_message',
            'message_id' => $message->id,
            'sender_id' => $sender->id,
            'sender_name' => $sender->name,
        ];

        $notification = ($this->queuePushNotification)($recipient, $title, $body, $data);
        ($this->sendPushNotification)($notification);
    }
}
