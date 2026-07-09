<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

class GetConversationsAction
{
    /**
     * Get all conversations for a user, grouped by the other participant.
     *
     * Returns a collection of the latest message per conversation partner,
     * ordered by most recent first, with the number of unread messages the
     * user has received in each conversation. When the user is an instructor,
     * each conversation also carries the other participant's student id
     * (null when the participant isn't one of their students).
     *
     * @return Collection<int, array{user: User, student_id: int|null, latest_message: Message, unread_count: int}>
     */
    public function __invoke(User $user): Collection
    {
        $userId = $user->id;

        $messages = Message::query()
            ->where('from', $userId)
            ->orWhere('to', $userId)
            ->with('sender:id,name', 'recipient:id,name')
            ->orderByDesc('created_at')
            ->get();

        $grouped = $messages->groupBy(function (Message $message) use ($userId) {
            return $message->from === $userId ? $message->to : $message->from;
        });

        $studentIdsByUserId = $this->studentIdsByUserId(
            $user,
            $grouped->keys()->map(fn ($id) => (int) $id)->all()
        );

        return $grouped->map(function (Collection $conversationMessages) use ($userId, $studentIdsByUserId) {
            /** @var Message $latestMessage */
            $latestMessage = $conversationMessages->first();
            $otherUser = $latestMessage->from === $userId
                ? $latestMessage->recipient
                : $latestMessage->sender;

            return [
                'user' => $otherUser,
                'student_id' => $studentIdsByUserId[$otherUser->id] ?? null,
                'latest_message' => $latestMessage,
                'unread_count' => $conversationMessages
                    ->filter(fn (Message $message) => $message->to === $userId && ! $message->isRead())
                    ->count(),
            ];
        })->sortByDesc(fn (array $conversation) => $conversation['latest_message']->created_at)
            ->values();
    }

    /**
     * Map participant user ids to the instructor's student ids.
     *
     * Empty for non-instructors, so student_id resolves to null for
     * admin/owner participants and student-authenticated users. Students
     * assigned to a different instructor also resolve to null.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function studentIdsByUserId(User $user, array $userIds): array
    {
        if ($userIds === [] || ! $user->isInstructor() || ! $user->instructor) {
            return [];
        }

        return Student::query()
            ->where('instructor_id', $user->instructor->id)
            ->whereIn('user_id', $userIds)
            ->pluck('id', 'user_id')
            ->all();
    }
}
