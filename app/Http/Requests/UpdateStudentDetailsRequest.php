<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Models\Student;
use Illuminate\Support\Arr;

/**
 * Web pupil profile "Edit details" form. Shares the app's update rules,
 * but status has its own form (`UpdateStudentStatusRequest`) and the
 * learner's name is required.
 */
class UpdateStudentDetailsRequest extends UpdateStudentRequest
{
    /**
     * Owners may edit any pupil; instructors only their own pupils.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        $student = $this->route('student');

        return $user->isInstructor()
            && $student instanceof Student
            && $student->instructor_id !== null
            && $student->instructor_id === $user->instructor?->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = Arr::except(parent::rules(), ['status']);

        $rules['first_name'] = ['required', 'string', 'max:255'];
        $rules['surname'] = ['required', 'string', 'max:255'];

        return $rules;
    }
}
