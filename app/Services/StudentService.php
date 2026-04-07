<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Student\AttachStudentToInstructorAction;
use App\Actions\Student\GetAllStudentsAction;
use App\Actions\Student\GetStudentByIdAction;
use App\Actions\Student\PickupPoint\CreatePickupPointAction;
use App\Actions\Student\PickupPoint\DeletePickupPointAction;
use App\Actions\Student\PickupPoint\GetStudentPickupPointsAction;
use App\Actions\Student\PickupPoint\SetDefaultPickupPointAction;
use App\Actions\Student\Status\RemoveStudentFromInstructorAction;
use App\Actions\Student\UpdateStudentAction;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class StudentService extends BaseService
{
    public function __construct(
        protected GetAllStudentsAction $getAllStudents,
        protected GetStudentByIdAction $getStudentById,
        protected CreatePupilAction $createPupil,
        protected UpdateStudentAction $updateStudent,
        protected RemoveStudentFromInstructorAction $removeStudent,
        protected GetStudentPickupPointsAction $getStudentPickupPoints,
        protected CreatePickupPointAction $createPickupPoint,
        protected DeletePickupPointAction $deletePickupPoint,
        protected SetDefaultPickupPointAction $setDefaultPickupPoint,
        protected AttachStudentToInstructorAction $attachStudentToInstructor
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
     * Create a new pupil (user + student record) assigned to an instructor.
     */
    public function create(Instructor $instructor, array $data): Student
    {
        $student = ($this->createPupil)($instructor, $data);

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
     * Remove a student from their instructor (soft remove, not hard delete).
     */
    public function remove(Student $student): Student
    {
        $instructor = $student->instructor;

        $result = ($this->removeStudent)($student);

        if ($instructor) {
            $this->invalidateInstructorStudentCache($instructor);
        }

        return $result;
    }

    /**
     * Attach a student to an instructor (resolved from PIN).
     */
    public function attachToInstructor(Student $student, Instructor $instructor): Student
    {
        $result = ($this->attachStudentToInstructor)($student, $instructor);

        $this->invalidateInstructorStudentCache($instructor);

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

    /**
     * Get all pickup points for a student.
     *
     * @return Collection<int, \App\Models\StudentPickupPoint>
     */
    public function getPickupPoints(Student $student): Collection
    {
        return ($this->getStudentPickupPoints)($student);
    }

    /**
     * Create a new pickup point for a student.
     *
     * @param  array{label: string, address: string, postcode: string, is_default?: bool}  $data
     */
    public function storePickupPoint(Student $student, array $data): \App\Models\StudentPickupPoint
    {
        return ($this->createPickupPoint)($student, $data);
    }

    /**
     * Delete a pickup point.
     */
    public function deletePickupPoint(\App\Models\StudentPickupPoint $pickupPoint): void
    {
        ($this->deletePickupPoint)($pickupPoint);
    }

    /**
     * Set a pickup point as the default for its student.
     */
    public function setDefaultPickupPoint(\App\Models\StudentPickupPoint $pickupPoint): \App\Models\StudentPickupPoint
    {
        return ($this->setDefaultPickupPoint)($pickupPoint);
    }
}
