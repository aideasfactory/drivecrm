<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstructorMergePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true && ! $this->user()->isRestrictedOwner();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from_instructor_id' => ['required', 'integer', 'exists:instructors,id'],
            'into_instructor_id' => ['required', 'integer', 'exists:instructors,id', 'different:from_instructor_id'],
            'take_email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_instructor_id.required' => 'Please select the old account to merge.',
            'from_instructor_id.exists' => 'The old account could not be found.',
            'into_instructor_id.required' => 'Please select the live account to keep.',
            'into_instructor_id.exists' => 'The live account could not be found.',
            'into_instructor_id.different' => 'The old and live accounts must be different instructors.',
        ];
    }
}
