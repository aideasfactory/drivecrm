<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignLessonResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'resource_ids' => ['required', 'array', 'min:1'],
            'resource_ids.*' => ['required', 'integer', 'exists:resources,id'],
        ];
    }
}
