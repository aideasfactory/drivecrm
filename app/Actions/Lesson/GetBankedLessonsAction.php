<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Models\Instructor;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

class GetBankedLessonsAction
{
    /**
     * Every lesson waiting to be rebooked (kept in its booking with no date),
     * longest-waiting first. Scoped to one instructor when given.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(?Instructor $instructor = null): Collection
    {
        return Lesson::query()
            ->banked()
            ->when($instructor, fn ($query) => $query->where('instructor_id', $instructor->id))
            ->with(['order.student', 'instructor.user', 'lessonPayment', 'bankedBy'])
            ->orderBy('banked_at')
            ->orderBy('id')
            ->get();
    }
}
