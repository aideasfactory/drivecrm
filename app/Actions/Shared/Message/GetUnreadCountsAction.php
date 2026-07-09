<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\Student;
use App\Models\User;

class GetUnreadCountsAction
{
    /**
     * Get unread message counts for a user.
     *
     * Returns the total number of unread messages plus a per-conversation
     * breakdown keyed by the sender's user id, in a single grouped query.
     * When the user is an instructor, each conversation also carries the
     * sender's student id (null when the sender isn't one of their students).
     *
     * @return array{total: int, conversations: array<int, array{user_id: int, student_id: int|null, unread_count: int}>}
     */
    public function __invoke(User $user): array
    {
        $counts = Message::query()
            ->where('to', $user->id)
            ->unread()
            ->selectRaw('`from` as sender_id, COUNT(*) as unread_count')
            ->groupBy('from')
            ->orderByDesc('unread_count')
            ->get();

        $studentIdsByUserId = $this->studentIdsByUserId(
            $user,
            $counts->pluck('sender_id')->map(fn ($id) => (int) $id)->all()
        );

        return [
            'total' => (int) $counts->sum('unread_count'),
            'conversations' => $counts->map(fn ($row) => [
                'user_id' => (int) $row->sender_id,
                'student_id' => $studentIdsByUserId[(int) $row->sender_id] ?? null,
                'unread_count' => (int) $row->unread_count,
            ])->values()->all(),
        ];
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
