<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstructorMergeSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Please enter a search term.',
            'q.min' => 'Enter at least 2 characters to search.',
        ];
    }
}
