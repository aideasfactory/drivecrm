<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveStudentProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.progress_subcategory_id' => ['required', 'integer', 'exists:progress_subcategories,id'],
            'scores.*.score' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
