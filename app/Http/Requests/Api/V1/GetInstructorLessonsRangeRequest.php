<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\DiaryDateSpan;
use Illuminate\Foundation\Http\FormRequest;

class GetInstructorLessonsRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'date_format:Y-m-d'],
            'to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from', new DiaryDateSpan],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The to date must be on or after from.',
        ];
    }
}
