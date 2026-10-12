<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * The reference of the manual payment (e.g. the Stripe transfer ID or the
     * bank payment reference), so the payout can be traced back to it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => 'Enter the payment reference from Stripe or your bank.',
            'reference.max' => 'The payment reference must be 255 characters or fewer.',
        ];
    }
}
