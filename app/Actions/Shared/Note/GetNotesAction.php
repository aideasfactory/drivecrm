<?php

declare(strict_types=1);

namespace App\Actions\Shared\Note;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GetNotesAction
{
    /**
     * Get paginated notes for an instructor or student.
     *
     * @param  Instructor|Student  $noteable  The entity to fetch notes for
     * @param  int  $perPage  Number of notes per page
     *
     * @throws InvalidArgumentException If noteable is not Instructor or Student
     */
    public function __invoke(Model $noteable, int $perPage = 20): LengthAwarePaginator
    {
        if (! $noteable instanceof Instructor && ! $noteable instanceof Student) {
            throw new InvalidArgumentException(
                'Noteable must be an instance of Instructor or Student'
            );
        }

        return $noteable->notes()->latest()->paginate($perPage);
    }
}
