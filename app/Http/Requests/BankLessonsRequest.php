<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LessonCancellationReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['single', 'future'])],
            'reason_code' => ['required', Rule::enum(LessonCancellationReason::class)],
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->reasonCode()?->requiresDetails() === true),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * The chosen reason (null until validated).
     */
    public function reasonCode(): ?LessonCancellationReason
    {
        return LessonCancellationReason::tryFrom((string) $this->input('reason_code'));
    }

    /**
     * Whether the later open lessons in the same booking are banked too.
     */
    public function appliesToFutureInOrder(): bool
    {
        return $this->input('scope') === 'future';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason_code.required' => 'Please choose a reason.',
            'reason_code.enum' => 'Please choose a valid reason.',
            'reason.required' => 'Please add details for this reason.',
            'reason.max' => 'The details cannot exceed 1000 characters.',
            'scope.in' => 'Scope must be either "single" or "future".',
        ];
    }
}
