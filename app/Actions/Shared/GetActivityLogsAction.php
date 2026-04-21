<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GetActivityLogsAction
{
    /**
     * Get paginated activity logs for an instructor or student, with optional filters.
     *
     * @param  Instructor|Student  $loggable  The entity whose logs we want
     * @param  array{category?: ?string, search?: ?string, per_page?: int}  $filters
     *
     * @throws InvalidArgumentException If loggable is not Instructor or Student
     */
    public function __invoke(Model $loggable, array $filters = []): LengthAwarePaginator
    {
        if (! $loggable instanceof Instructor && ! $loggable instanceof Student) {
            throw new InvalidArgumentException(
                'Loggable must be an instance of Instructor or Student'
            );
        }

        $query = $loggable->activityLogs()->recent();

        $category = $filters['category'] ?? null;
        if ($category !== null && $category !== '' && $category !== 'all') {
            $query->category($category);
        }

        $search = $filters['search'] ?? null;
        if ($search !== null && $search !== '') {
            $query->where('message', 'like', '%'.$search.'%');
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }
}
