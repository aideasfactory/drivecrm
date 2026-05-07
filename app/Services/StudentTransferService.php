<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\Transfer\ExecuteStudentTransferAction;
use App\Actions\Student\Transfer\GetOnboardedInstructorsAction;
use App\Actions\Student\Transfer\GetTransferableStudentsAction;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentTransferService extends BaseService
{
    public function __construct(
        protected GetTransferableStudentsAction $getTransferableStudents,
        protected GetOnboardedInstructorsAction $getOnboardedInstructors,
        protected ExecuteStudentTransferAction $executeTransfer,
    ) {}

    /**
     * @return Collection<int, Student>
     */
    public function getTransferableStudents(): Collection
    {
        return ($this->getTransferableStudents)();
    }

    /**
     * @return Collection<int, Instructor>
     */
    public function getOnboardedInstructors(): Collection
    {
        return ($this->getOnboardedInstructors)();
    }

    /**
     * @return array{
     *     student: Student,
     *     source_instructor: Instructor,
     *     destination_instructor: Instructor,
     *     moved_lessons: Collection<int, \App\Models\Lesson>,
     *     clashing_lessons: Collection<int, \App\Models\Lesson>,
     * }
     */
    public function transferStudent(Student $student, Instructor $destination, User $admin): array
    {
        return ($this->executeTransfer)($student, $destination, $admin);
    }
}
