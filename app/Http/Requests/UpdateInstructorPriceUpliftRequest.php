<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstructorPriceUpliftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * The uplift arrives in POUNDS per lesson (e.g. 5 or 5.50) and may be
     * negative for discounted instructors; the controller converts to pence.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'price_uplift' => ['required', 'numeric', 'between:-1000,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_uplift.required' => 'Please enter an uplift amount (0 for none).',
            'price_uplift.numeric' => 'The uplift must be a number in pounds per lesson.',
            'price_uplift.between' => 'The uplift must be between -£1,000 and £1,000 per lesson.',
        ];
    }
}
