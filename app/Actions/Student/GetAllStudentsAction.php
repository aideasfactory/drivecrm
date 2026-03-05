<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class GetAllStudentsAction
{
    /**
     * Get all students with their associated instructor and user.
     *
     * @return Collection<int, Student>
     */
    public function __invoke(): Collection
    {
        return Student::query()
            ->with(['instructor.user', 'user'])
            ->orderByDesc('created_at')
            ->get();
    }
}
