<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionTest;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetHazardPerceptionTestHistoryAction
{
    public function __invoke(Student $student, ?string $topic = null, int $perPage = 20): LengthAwarePaginator
    {
        return HazardPerceptionTest::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->when($topic, fn ($query) => $query->where('topic', $topic))
            ->orderByDesc('completed_at')
            ->paginate($perPage);
    }
}
