<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesDiaryDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GetInstructorLessonsRangeRequest extends FormRequest
{
    use ValidatesDiaryDateRange;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->dateRangeRules(required: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->dateRangeMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDateRangeSpan($validator);
    }
}
