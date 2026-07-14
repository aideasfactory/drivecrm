<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetTestHistoryAction
{
    public function __invoke(Student $student, ?string $category = null, ?string $mode = null, int $perPage = 20): LengthAwarePaginator
    {
        return MockTest::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($mode, fn ($query) => $query->where('mode', $mode))
            ->orderByDesc('completed_at')
            ->paginate($perPage);
    }
}
