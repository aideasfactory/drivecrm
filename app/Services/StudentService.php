<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Student\AttachStudentToInstructorAction;
use App\Actions\Student\DeleteStudentProfilePictureAction;
use App\Actions\Student\GetAllStudentsAction;
use App\Actions\Student\GetStudentByIdAction;
use App\Actions\Student\GetStudentInstructorProfileAction;
use App\Actions\Student\GetStudentPracticeHoursAction;
use App\Actions\Student\PickupPoint\CreatePickupPointAction;
use App\Actions\Student\PickupPoint\DeletePickupPointAction;
use App\Actions\Student\PickupPoint\GetStudentPickupPointsAction;
use App\Actions\Student\PickupPoint\SetDefaultPickupPointAction;
use App\Actions\Student\PickupPoint\UpdatePickupPointAction;
use App\Actions\Student\Status\RemoveStudentFromInstructorAction;
use App\Actions\Student\UpdateStudentAction;
use App\Actions\Student\UploadStudentProfilePictureAction;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

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
        protected UpdatePickupPointAction $updatePickupPoint,
        protected DeletePickupPointAction $deletePickupPoint,
        protected SetDefaultPickupPointAction $setDefaultPickupPoint,
        protected AttachStudentToInstructorAction $attachStudentToInstructor,
        protected UploadStudentProfilePictureAction $uploadProfilePicture,
        protected DeleteStudentProfilePictureAction $deleteProfilePictureAction,
        protected GetStudentInstructorProfileAction $getInstructorProfile,
        protected GetStudentPracticeHoursAction $getPracticeHours
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
     * Upload or replace a student's profile picture.
     */
    public function updateProfilePicture(Student $student, UploadedFile $file): Student
    {
        return ($this->uploadProfilePicture)($student, $file);
    }

    /**
     * Delete a student's profile picture.
     */
    public function deleteProfilePicture(Student $student): Student
    {
        return ($this->deleteProfilePictureAction)($student);
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
     * @return Collection<int, StudentPickupPoint>
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
    public function storePickupPoint(Student $student, array $data): StudentPickupPoint
    {
        return ($this->createPickupPoint)($student, $data);
    }

    /**
     * Update an existing pickup point. Re-geocodes if the postcode changed.
     *
     * @param  array{label: string, address: string, postcode: string, is_default?: bool}  $data
     */
    public function updatePickupPoint(StudentPickupPoint $pickupPoint, array $data): StudentPickupPoint
    {
        return ($this->updatePickupPoint)($pickupPoint, $data);
    }

    /**
     * Delete a pickup point.
     */
    public function deletePickupPoint(StudentPickupPoint $pickupPoint): void
    {
        ($this->deletePickupPoint)($pickupPoint);
    }

    /**
     * Set a pickup point as the default for its student.
     */
    public function setDefaultPickupPoint(StudentPickupPoint $pickupPoint): StudentPickupPoint
    {
        return ($this->setDefaultPickupPoint)($pickupPoint);
    }

    /**
     * Get the public profile of the student's attached instructor.
     */
    public function getInstructorProfile(Student $student): ?Instructor
    {
        if (! $student->instructor_id) {
            return null;
        }

        $key = $this->cacheKey('student', $student->id, 'instructor_profile');

        return $this->remember($key, fn () => ($this->getInstructorProfile)($student));
    }

    /**
     * Get the student's dashboard data (practice hours).
     *
     * @return array{practice_hours: array{completed: float, total: float}}
     */
    public function getDashboard(Student $student): array
    {
        $key = $this->cacheKey('student', $student->id, 'dashboard');

        return $this->remember($key, fn () => [
            'practice_hours' => ($this->getPracticeHours)($student),
        ]);
    }

    /**
     * Invalidate student dashboard caches.
     */
    public function invalidateStudentDashboardCache(Student $student): void
    {
        $this->invalidate([
            $this->cacheKey('student', $student->id, 'instructor_profile'),
            $this->cacheKey('student', $student->id, 'dashboard'),
        ]);
    }
}
