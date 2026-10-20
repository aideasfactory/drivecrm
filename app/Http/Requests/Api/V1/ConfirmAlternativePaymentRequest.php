<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirm a booking awaiting its first payment as paid by an alternative
 * method. Used by the mobile API and the web CRM.
 */
class ConfirmAlternativePaymentRequest extends FormRequest
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
            'accept_risks' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_risks.required' => 'Please confirm you accept the risks of an alternative payment.',
            'accept_risks.accepted' => 'Please confirm you accept the risks of an alternative payment.',
        ];
    }
}
