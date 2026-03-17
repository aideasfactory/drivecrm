<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Support\Collection;

class GetGroupedStudentsAction
{
    /**
     * Get instructor's students grouped by status with recent activity.
     *
     * @return array{active: Collection, passed: Collection, inactive: Collection, recent_activity: Collection}
     */
    public function __invoke(Instructor $instructor): array
    {
        $students = Student::where('instructor_id', $instructor->id)
            ->with(['user'])
            ->get();

        return [
            'active' => $students->where('status', 'active')->values(),
            'passed' => $students->where('status', 'passed')->values(),
            'inactive' => $students->where('status', 'inactive')->values(),
            'recent_activity' => $students->sortByDesc('updated_at')->take(5)->values(),
        ];
    }
}
