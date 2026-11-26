<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\LearnerTermsVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearnerTermsDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true && ! $this->user()->isRestrictedOwner();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $draftId = LearnerTermsVersion::query()->draft()->value('id');

        return [
            'version' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d+(\.\d+){0,2}$/',
                Rule::unique('learner_terms_versions', 'version')->ignore($draftId),
            ],
            'body' => ['required', 'string', 'max:200000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Use a version number like 1.0 or 1.1.',
            'version.unique' => 'That version number has already been used.',
            'body.required' => 'Add the terms text.',
        ];
    }
}
