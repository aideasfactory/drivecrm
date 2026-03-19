<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\CreateStudentAction;
use App\Actions\Student\DeleteStudentAction;
use App\Actions\Student\GetAllStudentsAction;
use App\Actions\Student\GetStudentByIdAction;
use App\Actions\Student\UpdateStudentAction;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class StudentService extends BaseService
{
    public function __construct(
        protected GetAllStudentsAction $getAllStudents,
        protected GetStudentByIdAction $getStudentById,
        protected CreateStudentAction $createStudent,
        protected UpdateStudentAction $updateStudent,
        protected DeleteStudentAction $deleteStudent
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
     * Create a new student record assigned to an instructor.
     */
    public function create(Instructor $instructor, array $data): Student
    {
        $student = ($this->createStudent)($instructor, $data);

        $this->invalidateInstructorStudentCache($instructor);

        return $student;
    }

    /**
     * Update an existing student record.
     */
    public function update(Student $student, array $data): Student
    {
        $updated = ($this->updateStudent)($student, $data);

        if ($student->instructor_id) {
            $this->invalidateInstructorStudentCache($student->instructor);
        }

        return $updated;
    }

    /**
     * Delete a student record.
     */
    public function delete(Student $student): bool
    {
        $instructor = $student->instructor;

        $result = ($this->deleteStudent)($student);

        if ($instructor) {
            $this->invalidateInstructorStudentCache($instructor);
        }

        return $result;
    }

    /**
     * Invalidate cached grouped students for an instructor.
     */
    private function invalidateInstructorStudentCache(Instructor $instructor): void
    {
        $this->invalidate(
            $this->cacheKey('instructor', $instructor->id, 'grouped_students')
        );
    }
}
