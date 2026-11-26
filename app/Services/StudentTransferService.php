<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\Transfer\ExecuteStudentTransferAction;
use App\Actions\Student\Transfer\GetOnboardedInstructorsAction;
use App\Actions\Student\Transfer\GetTransferableStudentsAction;
use App\Actions\Student\Transfer\PreviewStudentTransferAction;
use App\Enums\TransferLessonDecision;
use App\Enums\TransferReason;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StudentTransferService extends BaseService
{
    public function __construct(
        protected GetTransferableStudentsAction $getTransferableStudents,
        protected GetOnboardedInstructorsAction $getOnboardedInstructors,
        protected PreviewStudentTransferAction $previewTransfer,
        protected ExecuteStudentTransferAction $executeTransfer,
    ) {}

    /**
     * @return EloquentCollection<int, Student>
     */
    public function getTransferableStudents(?string $search = null, ?int $limit = null): EloquentCollection
    {
        return ($this->getTransferableStudents)($search, $limit);
    }

    /**
     * @return EloquentCollection<int, Instructor>
     */
    public function getOnboardedInstructors(?string $search = null, ?int $limit = null): EloquentCollection
    {
        return ($this->getOnboardedInstructors)($search, $limit);
    }

    /**
     * @return array{
     *     source: Instructor,
     *     destination: Instructor,
     *     past_unsigned: Collection<int, Lesson>,
     *     future_free: Collection<int, Lesson>,
     *     future_clashes: Collection<int, array{lesson: Lesson, clashes_with: string, can_unschedule: bool}>,
     *     unscheduled: Collection<int, Lesson>,
     *     pending_orders: Collection<int, Order>,
     * }
     */
    public function previewTransfer(Student $student, Instructor $destination): array
    {
        return ($this->previewTransfer)($student, $destination);
    }

    /**
     * @param  array<int, TransferLessonDecision>  $pastLessonDecisions
     * @return array{
     *     student: Student,
     *     source_instructor: Instructor,
     *     destination_instructor: Instructor,
     *     moved_lessons: Collection<int, Lesson>,
     *     clashing_lessons: Collection<int, Lesson>,
     *     unscheduled_lessons: Collection<int, Lesson>,
     *     missed_lessons: Collection<int, Lesson>,
     *     cancelled_orders: Collection<int, Order>,
     *     problems: list<string>,
     * }
     */
    public function transferStudent(
        Student $student,
        Instructor $destination,
        User $admin,
        TransferReason $reason,
        ?string $notes = null,
        array $pastLessonDecisions = [],
    ): array {
        return ($this->executeTransfer)($student, $destination, $admin, $reason, $notes, $pastLessonDecisions);
    }
}
