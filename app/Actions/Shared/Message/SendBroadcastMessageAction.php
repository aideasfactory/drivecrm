<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class SendBroadcastMessageAction
{
    /**
     * Send a broadcast message to multiple recipients.
     *
     * Each created row triggers MessageObserver, which emails and pushes to
     * the recipient (no per-row activity log — the caller writes a single
     * summary entry for the whole broadcast).
     *
     * @param  User  $sender  The user sending the message
     * @param  array<int>  $recipientUserIds  Array of recipient user IDs
     * @param  string  $message  The message content
     * @return Collection Collection of created Message models
     */
    public function __invoke(User $sender, array $recipientUserIds, string $message): Collection
    {
        $messages = collect();

        foreach ($recipientUserIds as $recipientUserId) {
            $messages->push(
                Message::create([
                    'from' => $sender->id,
                    'to' => $recipientUserId,
                    'message' => $message,
                    'type' => MessageType::BROADCAST,
                ])
            );
        }

        return $messages;
    }
}
