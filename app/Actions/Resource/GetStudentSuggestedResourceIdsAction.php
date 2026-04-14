<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetStudentSuggestedResourceIdsAction
{
    /**
     * Get the IDs of resources suggested to a student via lesson sign-offs.
     *
     * Queries the lesson_resource pivot through the student's lessons.
     *
     * @return Collection<int, int>
     */
    public function __invoke(Student $student): Collection
    {
        return DB::table('lesson_resource')
            ->join('lessons', 'lessons.id', '=', 'lesson_resource.lesson_id')
            ->join('orders', 'orders.id', '=', 'lessons.order_id')
            ->where('orders.student_id', $student->id)
            ->distinct()
            ->pluck('lesson_resource.resource_id');
    }
}
