<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LessonCancellationReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelBankedLessonRequest extends FormRequest
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason_code.required' => 'Please choose a reason for cancelling this lesson.',
            'reason_code.enum' => 'Please choose a valid cancellation reason.',
            'reason.required' => 'Please add details for this cancellation.',
            'reason.max' => 'The details cannot exceed 1000 characters.',
        ];
    }
}
