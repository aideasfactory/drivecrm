<?php

declare(strict_types=1);

namespace App\Actions\Bird;

use App\Models\BirdConversation;
use App\Models\BirdMessage;
use Carbon\CarbonImmutable;

class StoreBirdConversationAction
{
    /**
     * Upsert one mirrored conversation and its full message history.
     *
     * Both tables key on Bird's own UUIDs, so calling this repeatedly for the
     * same conversation is idempotent.
     *
     * @param  array{id: string, contact_name: string, contact_email: string, contact_phone: string, last_message: string, status: string, channel_id: string, updated_at: string}  $conversation
     * @param  array<int, array{id: string, sender_name: string, sender_type: string, text: string, sent_at: string}>  $messages
     * @return int Number of messages upserted
     */
    public function __invoke(array $conversation, array $messages): int
    {
        BirdConversation::query()->upsert(
            [[
                'id' => $conversation['id'],
                'contact_name' => $conversation['contact_name'],
                'contact_email' => $conversation['contact_email'],
                'contact_phone' => $conversation['contact_phone'],
                'last_message' => $conversation['last_message'],
                'status' => $conversation['status'],
                'channel_id' => $conversation['channel_id'] !== '' ? $conversation['channel_id'] : null,
                'last_activity_at' => $this->toDateTime($conversation['updated_at']),
            ]],
            ['id'],
            ['contact_name', 'contact_email', 'contact_phone', 'last_message', 'status', 'channel_id', 'last_activity_at'],
        );

        $rows = collect($messages)
            ->filter(fn (array $message): bool => $message['id'] !== '')
            ->map(fn (array $message): array => [
                'id' => $message['id'],
                'bird_conversation_id' => $conversation['id'],
                'sender_type' => $message['sender_type'],
                'sender_name' => $message['sender_name'],
                'text' => $message['text'],
                'sent_at' => $this->toDateTime($message['sent_at']),
            ]);

        foreach ($rows->chunk(200) as $chunk) {
            BirdMessage::query()->upsert(
                $chunk->values()->all(),
                ['id'],
                ['sender_type', 'sender_name', 'text', 'sent_at'],
            );
        }

        return $rows->count();
    }

    private function toDateTime(string $value): ?CarbonImmutable
    {
        return $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
