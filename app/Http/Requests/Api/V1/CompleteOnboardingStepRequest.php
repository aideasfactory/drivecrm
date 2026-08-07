<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Instructor;
use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingStepRequest extends FormRequest
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
            'step' => ['required', 'integer', 'between:1,'.Instructor::APP_ONBOARDING_TOTAL_STEPS],
        ];
    }
}
