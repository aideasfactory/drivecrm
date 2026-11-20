<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonDeletionRequestStatus;
use App\Models\LessonDeletionRequest;
use Illuminate\Database\Eloquent\Collection;

class GetLessonDeletionRequestsAction
{
    /**
     * Pending requests oldest first (the queue to work through), or the most
     * recently decided and cancelled ones newest first (history), with what
     * is needed to show each row and re-check its lesson.
     *
     * @return Collection<int, LessonDeletionRequest>
     */
    public function __invoke(bool $pending = true, int $historyLimit = 100): Collection
    {
        return LessonDeletionRequest::query()
            ->with([
                'lesson.order',
                'lesson.lessonPayment',
                'lesson.payout',
                'lesson.instructorFinances:id,lesson_id',
                'decidedBy:id,name',
            ])
            ->when(
                $pending,
                fn ($query) => $query->where('status', LessonDeletionRequestStatus::PENDING)->oldest('requested_at')->oldest('id'),
                fn ($query) => $query->where('status', '!=', LessonDeletionRequestStatus::PENDING)
                    ->latest('updated_at')
                    ->latest('id')
                    ->limit($historyLimit),
            )
            ->get();
    }
}
