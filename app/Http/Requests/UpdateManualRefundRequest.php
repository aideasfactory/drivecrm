<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManualRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * Whether the payment or booking is ticked as refunded outside the
     * platform (true) or the tick is being removed (false).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'refunded' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refunded.required' => 'Choose whether this has been refunded.',
            'refunded.boolean' => 'The refunded value must be true or false.',
        ];
    }
}
