<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Student\AttachStudentToInstructorAction;
use App\Actions\Student\DeleteStudentAction;
use App\Actions\Student\DeleteStudentProfilePictureAction;
use App\Actions\Student\GetAllStudentsAction;
use App\Actions\Student\GetStudentBookingDetailsAction;
use App\Actions\Student\GetStudentByIdAction;
use App\Actions\Student\GetStudentInstructorProfileAction;
use App\Actions\Student\GetStudentPracticeHoursAction;
use App\Actions\Student\PickupPoint\CreatePickupPointAction;
use App\Actions\Student\PickupPoint\DeletePickupPointAction;
use App\Actions\Student\PickupPoint\GetDefaultPickupPointAction;
use App\Actions\Student\PickupPoint\GetStudentPickupPointsAction;
use App\Actions\Student\PickupPoint\SetDefaultPickupPointAction;
use App\Actions\Student\PickupPoint\UpdatePickupPointAction;
use App\Actions\Student\ResendStudentInviteAction;
use App\Actions\Student\SetStudentCashPaymentsAction;
use App\Actions\Student\Status\RemoveStudentFromInstructorAction;
use App\Actions\Student\UpdateStudentAction;
use App\Actions\Student\UploadStudentProfilePictureAction;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StudentService extends BaseService
{
    public function __construct(
        protected GetAllStudentsAction $getAllStudents,
        protected GetStudentByIdAction $getStudentById,
        protected CreatePupilAction $createPupil,
        protected ResendStudentInviteAction $resendStudentInvite,
        protected UpdateStudentAction $updateStudent,
        protected RemoveStudentFromInstructorAction $removeStudent,
        protected DeleteStudentAction $deleteStudent,
        protected GetStudentPickupPointsAction $getStudentPickupPoints,
        protected CreatePickupPointAction $createPickupPoint,
        protected UpdatePickupPointAction $updatePickupPoint,
        protected DeletePickupPointAction $deletePickupPoint,
        protected SetDefaultPickupPointAction $setDefaultPickupPoint,
        protected AttachStudentToInstructorAction $attachStudentToInstructor,
        protected UploadStudentProfilePictureAction $uploadProfilePicture,
        protected DeleteStudentProfilePictureAction $deleteProfilePictureAction,
        protected GetStudentInstructorProfileAction $getInstructorProfile,
        protected GetStudentPracticeHoursAction $getPracticeHours,
        protected SetStudentCashPaymentsAction $setStudentCashPayments,
        protected GetStudentBookingDetailsAction $getStudentBookingDetails,
        protected GetDefaultPickupPointAction $getDefaultPickupPointAction
    ) {}

    /**
     * Pickup address and date of birth captured on the pupil's booking form,
     * used when the pupil has no pickup point or stored date of birth.
     *
     * @return array{address: string|null, postcode: string|null, date_of_birth: string|null}
     */
    public function getBookingDetails(Student $student): array
    {
        return ($this->getStudentBookingDetails)($student);
    }

    /**
     * The pupil's default pickup point (or their first one when none is flagged).
     */
    public function getDefaultPickupPoint(Student $student): ?StudentPickupPoint
    {
        return ($this->getDefaultPickupPointAction)($student);
    }

    /**
     * Update a pupil's details from the CRM pupil profile. The address is the
     * pupil's default pickup point: it is updated when one exists, otherwise
     * a new default pickup point is created. Both go through the pickup point
     * actions so the postcode is geocoded.
     *
     * @param  array<string, mixed>  $data  Student fields, plus an optional `pickup_point` array{label: string, address: string, postcode: string}
     */
    public function updateDetails(Student $student, array $data): Student
    {
        $pickupPoint = $data['pickup_point'] ?? null;
        unset($data['pickup_point']);

        return DB::transaction(function () use ($student, $data, $pickupPoint): Student {
            $updated = $this->update($student, $data);

            if (is_array($pickupPoint)) {
                $this->saveDefaultPickupPoint($student, $pickupPoint);
            }

            return $updated;
        });
    }

    /**
     * Create or update the pupil's default pickup point. An unchanged pickup
     * point is left alone.
     *
     * @param  array{label: string, address: string, postcode: string}  $pickupPoint
     */
    private function saveDefaultPickupPoint(Student $student, array $pickupPoint): StudentPickupPoint
    {
        $data = [
            'label' => $pickupPoint['label'],
            'address' => $pickupPoint['address'],
            'postcode' => $pickupPoint['postcode'],
            'is_default' => true,
        ];

        $existing = $this->getDefaultPickupPoint($student);

        if (! $existing) {
            return ($this->createPickupPoint)($student, $data);
        }

        $isUnchanged = $existing->is_default
            && $existing->label === $data['label']
            && $existing->address === $data['address']
            && strtoupper(str_replace(' ', '', $existing->postcode)) === strtoupper(str_replace(' ', '', $data['postcode']));

        if ($isUnchanged) {
            return $existing;
        }

        return ($this->updatePickupPoint)($existing, $data);
    }

    /**
     * Turn cash payments on or off for a pupil.
     */
    public function setCashPayments(Student $student, bool $enabled, ?User $changedBy = null): Student
    {
        return ($this->setStudentCashPayments)($student, $enabled, $changedBy);
    }

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
     * Resend the app login invite to an existing student.
     *
     * Issues a fresh temporary password and re-sends the welcome notification.
     * Returns false when the student has no linked user account or instructor.
     */
    public function resendInvite(Student $student): bool
    {
        return ($this->resendStudentInvite)($student);
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
     * Soft-delete a learner profile and lock the linked login.
     */
    public function deleteProfile(Student $student, ?User $deletedBy = null): void
    {
        $instructor = $student->instructor;

        ($this->deleteStudent)($student, $deletedBy);

        if ($instructor) {
            $this->invalidateInstructorStudentCache($instructor);
        }

        $this->invalidateStudentDashboardCache($student);
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
