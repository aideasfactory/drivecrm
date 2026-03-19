<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateStudentChecklistItemRequest;
use App\Http\Resources\V1\StudentChecklistItemCollection;
use App\Http\Resources\V1\StudentChecklistItemResource;
use App\Models\Student;
use App\Models\StudentChecklistItem;
use App\Services\StudentChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentChecklistItemController extends Controller
{
    public function __construct(
        protected StudentChecklistService $studentChecklistService
    ) {}

    /**
     * Return all checklist items for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function index(Request $request, Student $student): StudentChecklistItemCollection
    {
        Gate::authorize('viewAny', [StudentChecklistItem::class, $student]);

        $items = $this->studentChecklistService->getChecklist($student);

        return new StudentChecklistItemCollection($items);
    }

    /**
     * Update a single checklist item for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function update(UpdateStudentChecklistItemRequest $request, Student $student, StudentChecklistItem $checklistItem): StudentChecklistItemResource
    {
        Gate::authorize('update', [StudentChecklistItem::class, $student]);

        abort_unless($checklistItem->student_id === $student->id, 404);

        $checklistItem = $this->studentChecklistService->updateChecklistItem(
            $checklistItem,
            $request->validated()
        );

        return new StudentChecklistItemResource($checklistItem);
    }
}
