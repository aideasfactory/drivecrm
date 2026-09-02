<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptSlotOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_mode' => ['sometimes', 'string', Rule::in([PaymentMode::UPFRONT->value, PaymentMode::WEEKLY->value])],
        ];
    }
}
