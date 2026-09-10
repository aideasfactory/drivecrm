<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resource_ids' => ['required', 'array', 'min:1'],
            'resource_ids.*' => ['integer', 'distinct', 'exists:resources,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resource_ids.required' => 'The resource order is required.',
            'resource_ids.*.exists' => 'One or more resources do not exist.',
        ];
    }
}
