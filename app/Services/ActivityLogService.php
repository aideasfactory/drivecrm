<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\GetActivityLogsAction;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogService extends BaseService
{
    public function __construct(
        protected GetActivityLogsAction $getActivityLogs,
    ) {}

    /**
     * Get paginated activity logs for a student, with optional filters.
     *
     * @param  array{category?: ?string, search?: ?string, per_page?: int}  $filters
     */
    public function getStudentActivityLogs(Student $student, array $filters = []): LengthAwarePaginator
    {
        return ($this->getActivityLogs)($student, $filters);
    }
}
