<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TestHistoryRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'in:Car,ADI,Motorcycle,LGV-PCV,Mixed'],
            'mode' => ['nullable', 'string', 'in:mock,practice'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
