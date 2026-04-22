<?php

declare(strict_types=1);

namespace App\Http\Requests\ProgressTracker;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCategoriesRequest extends FormRequest
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
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct'],
        ];
    }
}
