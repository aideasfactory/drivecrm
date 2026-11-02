<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\SupportTicketArchive;
use App\Models\User;
use Illuminate\Support\Collection;

class GetSupportConversationsAction
{
    /**
     * Get the shared support inbox: every conversation between any owner
     * (admin) and a non-owner participant, grouped by participant.
     *
     * The mobile app addresses support messages to a single admin user id,
     * so the inbox is team-wide rather than scoped to the logged-in admin.
     * A conversation is archived only while its most recent archive record
     * (from any admin) is at or after the latest message — a newer message
     * automatically reopens the ticket.
     *
     * @return Collection<int, array{user: User, latest_message: Message, is_archived: bool}>
     */
    public function __invoke(): Collection
    {
        $ownerIds = User::query()
            ->where('role', UserRole::OWNER)
            ->pluck('id')
            ->all();

        if ($ownerIds === []) {
            return collect();
        }

        $messages = Message::query()
            ->where(fn ($query) => $query->whereIn('from', $ownerIds)->whereNotIn('to', $ownerIds))
            ->orWhere(fn ($query) => $query->whereIn('to', $ownerIds)->whereNotIn('from', $ownerIds))
            ->with('sender:id,name,role', 'recipient:id,name,role')
            ->orderByDesc('created_at')
            ->get();

        $grouped = $messages->groupBy(
            fn (Message $message): int => (int) (in_array($message->from, $ownerIds) ? $message->to : $message->from)
        );

        $archivedAtByParticipant = SupportTicketArchive::query()
            ->whereIn('participant_id', $grouped->keys()->all())
            ->selectRaw('participant_id, MAX(archived_at) as archived_at')
            ->groupBy('participant_id')
            ->get()
            ->keyBy('participant_id');

        return $grouped->map(function (Collection $conversationMessages) use ($ownerIds, $archivedAtByParticipant): array {
            /** @var Message $latestMessage */
            $latestMessage = $conversationMessages->first();
            $participant = in_array($latestMessage->from, $ownerIds)
                ? $latestMessage->recipient
                : $latestMessage->sender;

            $archivedAt = $archivedAtByParticipant->get($participant->id)?->archived_at;
            $latestAt = $latestMessage->created_at;

            return [
                'user' => $participant,
                'latest_message' => $latestMessage,
                'is_archived' => $archivedAt !== null
                    && $latestAt !== null
                    && $archivedAt->greaterThanOrEqualTo($latestAt),
            ];
        })->sortByDesc(fn (array $conversation) => $conversation['latest_message']->created_at)
            ->values();
    }
}
