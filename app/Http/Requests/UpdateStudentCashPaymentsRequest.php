<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentCashPaymentsRequest extends FormRequest
{
    /**
     * Only owners with full admin access may turn cash payments on or off —
     * the same people who can book cash orders.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasFullOwnerAccess() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cash_payments_enabled' => ['required', 'boolean'],
        ];
    }
}
