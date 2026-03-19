<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\GetAllStudentsAction;
use App\Actions\Student\GetStudentByIdAction;
use App\Actions\Student\PickupPoint\GetStudentPickupPointsAction;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class StudentService extends BaseService
{
    public function __construct(
        protected GetAllStudentsAction $getAllStudents,
        protected GetStudentByIdAction $getStudentById,
        protected GetStudentPickupPointsAction $getStudentPickupPoints
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

    /**
     * Get a single student by ID with relationships.
     */
    public function getById(int $id): Student
    {
        return ($this->getStudentById)($id);
    }

    /**
     * Get all pickup points for a student.
     *
     * @return Collection<int, \App\Models\StudentPickupPoint>
     */
    public function getPickupPoints(Student $student): Collection
    {
        return ($this->getStudentPickupPoints)($student);
    }
}
