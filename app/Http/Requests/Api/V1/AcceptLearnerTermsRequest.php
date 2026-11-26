<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\LearnerTermsAcceptance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptLearnerTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->student !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:20'],
            'platform' => ['required', 'string', Rule::in(LearnerTermsAcceptance::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
