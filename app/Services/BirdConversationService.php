<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bird\GetAllBirdMessagesAction;
use App\Actions\Bird\GetBirdConversationMessagesAction;
use App\Actions\Bird\GetBirdConversationsAction;
use App\Actions\Bird\StoreBirdConversationAction;
use App\Models\BirdConversation;
use App\Models\BirdMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads AI Employee chat history from the Bird Conversations API.
 *
 * AI Employee chats are ordinary Bird conversations on the channel the AI
 * Employee is connected to, so this service lists workspace conversations
 * (optionally filtered to that channel via BIRD_AI_CHANNEL_ID) and fetches
 * their message history.
 *
 * Note: the access key must carry Conversations read permissions. Bird masks
 * missing permissions as 404 "The resource doesn't exist or you don't have
 * access to it" — a key scoped only for contact upserts will fail here.
 *
 * Docs: https://docs.bird.com/api/conversations-api
 */
class BirdConversationService extends BaseService
{
    private const BASE_URL = 'https://api.bird.com';

    private const PAGE_SIZE = 100;

    public function __construct(
        protected StoreBirdConversationAction $storeBirdConversation,
        protected GetBirdConversationsAction $getBirdConversations,
        protected GetBirdConversationMessagesAction $getBirdConversationMessages,
        protected GetAllBirdMessagesAction $getAllBirdMessages,
    ) {}

    /**
     * Mirror Bird conversations and messages into the local database.
     *
     * Full mode walks every conversation. Incremental mode (the default, used
     * by the nightly schedule and the UI's Sync now button) stops once it
     * reaches conversations with no activity since a day before the newest
     * stored activity — Bird lists newest-first, so everything after that is
     * already mirrored.
     *
     * @return array{conversations: int, messages: int}
     */
    public function sync(bool $full = false): array
    {
        $cutoff = null;

        if (! $full) {
            $latestActivity = BirdConversation::query()->max('last_activity_at');
            $cutoff = $latestActivity !== null
                ? CarbonImmutable::parse($latestActivity)->subDay()
                : null;
        }

        $conversationCount = 0;
        $messageCount = 0;
        $pageToken = null;

        do {
            $page = $this->listConversations($pageToken);
            $reachedCutoff = false;

            foreach ($page['conversations'] as $conversation) {
                $updatedAt = $conversation['updated_at'] !== ''
                    ? CarbonImmutable::parse($conversation['updated_at'])
                    : null;

                if ($cutoff !== null && $updatedAt !== null && $updatedAt->lessThan($cutoff)) {
                    $reachedCutoff = true;

                    continue;
                }

                $messages = $this->getMessages($conversation['id']);
                $messageCount += ($this->storeBirdConversation)($conversation, $messages);
                $conversationCount++;
            }

            $pageToken = $reachedCutoff ? null : $page['next_page_token'];
        } while ($pageToken !== null);

        return ['conversations' => $conversationCount, 'messages' => $messageCount];
    }

    /**
     * All mirrored conversations for the integrations screen, newest first.
     *
     * @return array{conversations: array<int, array<string, mixed>>, last_synced_at: string|null}
     */
    public function getStoredConversations(): array
    {
        $rows = ($this->getBirdConversations)()
            ->map(fn (BirdConversation $conversation): array => [
                'id' => $conversation->id,
                'contact_name' => $conversation->contact_name,
                'contact_email' => $conversation->contact_email,
                'contact_phone' => $conversation->contact_phone,
                'last_message' => (string) $conversation->last_message,
                'last_contact_message' => (string) $conversation->last_contact_message,
                'status' => $conversation->status,
                'channel_id' => (string) $conversation->channel_id,
                'messages_count' => $conversation->messages_count,
                'last_contact_message_at' => $conversation->last_contact_message_at !== null
                    ? CarbonImmutable::parse($conversation->last_contact_message_at)->toIso8601String()
                    : '',
                'updated_at' => $conversation->last_activity_at?->toIso8601String() ?? '',
            ])
            ->all();

        $lastSyncedAt = BirdConversation::query()->max('updated_at');

        return [
            'conversations' => $rows,
            'last_synced_at' => $lastSyncedAt !== null
                ? CarbonImmutable::parse($lastSyncedAt)->toIso8601String()
                : null,
        ];
    }

    /**
     * One mirrored conversation's messages in chronological order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStoredMessages(string $conversationId): array
    {
        return ($this->getBirdConversationMessages)($conversationId)
            ->map(fn (BirdMessage $message): array => [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'sender_type' => $message->sender_type,
                'text' => (string) $message->text,
                'sent_at' => $message->sent_at?->toIso8601String() ?? '',
            ])
            ->all();
    }

    /**
     * Every mirrored message with contact details — the full-dump rows the
     * client-side CSV is built from.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllStoredMessages(): array
    {
        return ($this->getAllBirdMessages)()
            ->map(fn (BirdMessage $message): array => [
                'id' => $message->id,
                'conversation_id' => $message->bird_conversation_id,
                'contact_name' => $message->conversation?->contact_name ?? '',
                'contact_email' => $message->conversation?->contact_email ?? '',
                'contact_phone' => $message->conversation?->contact_phone ?? '',
                'sender_name' => $message->sender_name,
                'sender_type' => $message->sender_type,
                'text' => (string) $message->text,
                'sent_at' => $message->sent_at?->toIso8601String() ?? '',
            ])
            ->all();
    }

    /**
     * List conversations, newest activity first.
     *
     * @return array{conversations: array<int, array<string, mixed>>, next_page_token: string|null}
     */
    public function listConversations(?string $pageToken = null): array
    {
        $query = ['limit' => self::PAGE_SIZE];

        if ($pageToken !== null && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $data = $this->get('/conversations', $query);

        $channelId = $this->stringOrNull(config('services.bird.ai_channel_id'));

        $rows = collect($data['results'] ?? [])
            ->filter(function (array $conversation) use ($channelId): bool {
                if ($channelId !== null) {
                    return ($conversation['channelId'] ?? null) === $channelId;
                }

                // Default to conversations the AI Employee took part in —
                // its participant type is "bot" (verified live).
                return collect($conversation['featuredParticipants'] ?? [])
                    ->contains(fn (array $participant): bool => ($participant['type'] ?? null) === 'bot');
            })
            ->map(fn (array $conversation): array => $this->mapConversation($conversation))
            ->sortByDesc('updated_at')
            ->values()
            ->all();

        return [
            'conversations' => $rows,
            'next_page_token' => $this->stringOrNull($data['nextPageToken'] ?? null),
        ];
    }

    /**
     * Fetch the full message history of one conversation, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(string $conversationId): array
    {
        $messages = [];
        $pageToken = null;

        do {
            $query = ['limit' => self::PAGE_SIZE];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $data = $this->get(sprintf('/conversations/%s/messages', $conversationId), $query);

            foreach ($data['results'] ?? [] as $message) {
                $messages[] = $this->mapMessage($message);
            }

            $pageToken = $this->stringOrNull($data['nextPageToken'] ?? null);
        } while ($pageToken !== null);

        return collect($messages)->sortBy('sent_at')->values()->all();
    }

    /**
     * Perform an authenticated GET against the Bird Conversations API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $apiKey = (string) config('services.bird.conversations_api_key', '');
        $workspaceId = (string) config('services.bird.workspace_id', '');

        if ($apiKey === '' || $workspaceId === '') {
            throw new RuntimeException('Bird API credentials are not configured (BIRD_CONVERSATIONS_API_KEY or BIRD_API_KEY, plus BIRD_WORKSPACE_ID).');
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'AccessKey '.$apiKey])
            ->timeout(30)
            ->get(self::BASE_URL.'/workspaces/'.$workspaceId.$path, $query);

        $this->assertSuccessful($response, $path);

        return $response->json() ?? [];
    }

    /**
     * Map a Bird conversation onto the shape the integrations screen renders.
     *
     * The customer is the participant of type "contact"; Bird embeds their
     * details in featuredParticipants.
     *
     * @param  array<string, mixed>  $conversation
     * @return array{id: string, contact_name: string, contact_email: string, contact_phone: string, last_message: string, status: string, channel_id: string, updated_at: string}
     */
    private function mapConversation(array $conversation): array
    {
        $contact = collect($conversation['featuredParticipants'] ?? [])
            ->first(fn (array $participant): bool => ($participant['type'] ?? null) === 'contact');

        // WhatsApp/SMS contacts identify by phone number, web/email ones by
        // email address — surface whichever identifier the contact has.
        $identifierKey = $contact['contact']['identifierKey'] ?? null;
        $identifierValue = (string) ($contact['contact']['identifierValue'] ?? '');
        $email = $identifierKey === 'emailaddress' ? $identifierValue : '';
        $phone = $identifierKey === 'phonenumber' ? $identifierValue : '';

        return [
            'id' => (string) ($conversation['id'] ?? ''),
            'contact_name' => (string) ($contact['displayName'] ?? ($conversation['name'] ?? '')),
            'contact_email' => $email,
            'contact_phone' => $phone,
            'last_message' => $this->extractText($conversation['lastMessage'] ?? []),
            'status' => (string) ($conversation['status'] ?? ''),
            'channel_id' => (string) ($conversation['channelId'] ?? ''),
            'updated_at' => (string) ($conversation['updatedAt'] ?? ($conversation['createdAt'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, sender_name: string, sender_type: string, text: string, sent_at: string}
     */
    private function mapMessage(array $message): array
    {
        return [
            'id' => (string) ($message['id'] ?? ''),
            'sender_name' => (string) ($message['sender']['displayName'] ?? ''),
            'sender_type' => (string) ($message['sender']['type'] ?? ''),
            'text' => $this->extractText($message),
            'sent_at' => (string) ($message['createdAt'] ?? ''),
        ];
    }

    /**
     * Pull displayable text out of a Bird message body, falling back to a
     * placeholder for non-text payloads (images, carousels, etc.).
     *
     * @param  array<string, mixed>  $message
     */
    private function extractText(array $message): string
    {
        $body = $message['body'] ?? [];
        // Full message objects carry body.text.text / body.html.text; the
        // lastMessage embedded in a conversation only has preview.text.
        $text = $body['text']['text'] ?? $body['html']['text'] ?? $message['preview']['text'] ?? null;

        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $type = $body['type'] ?? ($message['type'] ?? null);

        return is_string($type) && $type !== '' ? sprintf('[%s message]', $type) : '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertSuccessful(Response $response, string $path): void
    {
        if (! $response->failed()) {
            return;
        }

        $hint = in_array($response->status(), [403, 404], true)
            ? ' The access key lacks Conversations read permissions — check the key\'s roles in Bird under Settings → Access keys.'
            : '';

        throw new RuntimeException(sprintf(
            'Bird Conversations API request to %s failed: HTTP %d %s%s',
            $path,
            $response->status(),
            (string) $response->body(),
            $hint,
        ));
    }
}
