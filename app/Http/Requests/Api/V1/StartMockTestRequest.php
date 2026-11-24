<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartMockTestRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'in:Car'],
            'topic' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'in:mock,practice'],
            'question_count' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
