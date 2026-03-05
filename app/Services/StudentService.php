<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\GetAllStudentsAction;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class StudentService
{
    public function __construct(
        protected GetAllStudentsAction $getAllStudents
    ) {}

    /**
     * Get all students with instructor relationships.
     *
     * @return Collection<int, Student>
     */
    public function getAll(): Collection
    {
        return ($this->getAllStudents)();
    }
}
