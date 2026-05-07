<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\HmrcMoney;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SubmitVatReturnRequest extends FormRequest
{
    private const POUND_FIELDS = [
        'vat_due_sales',
        'vat_due_acquisitions',
        'total_vat_due',
        'vat_reclaimed_curr_period',
        'net_vat_due',
        'total_value_sales_ex_vat',
        'total_value_purchases_ex_vat',
        'total_value_goods_supplied_ex_vat',
        'total_acquisitions_ex_vat',
    ];

    public function authorize(): bool
    {
        $instructor = $this->user()?->instructor;

        return $instructor !== null
            && (bool) $instructor->vat_registered
            && is_string($instructor->vrn)
            && $instructor->vrn !== '';
    }

    /**
     * Convert pound-string inputs into pence integers BEFORE validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (self::POUND_FIELDS as $field) {
            if ($this->has($field)) {
                $merge[$field.'_pence'] = $this->safePence($this->input($field));
            }
        }

        $merge['attestation'] = $this->boolean('attestation');

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // VAT amount boxes (1–5) — non-negative integer pence.
        // Value boxes (6–9) — also non-negative integer pence (we store pence and submit
        // whole pounds at HMRC; the action layer rounds).
        return [
            'vat_due_sales_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'vat_due_acquisitions_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'total_vat_due_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'vat_reclaimed_curr_period_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'net_vat_due_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'total_value_sales_ex_vat_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'total_value_purchases_ex_vat_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'total_value_goods_supplied_ex_vat_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'total_acquisitions_ex_vat_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'attestation' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $box1 = (int) $this->input('vat_due_sales_pence');
            $box2 = (int) $this->input('vat_due_acquisitions_pence');
            $box3 = (int) $this->input('total_vat_due_pence');
            $box4 = (int) $this->input('vat_reclaimed_curr_period_pence');
            $box5 = (int) $this->input('net_vat_due_pence');

            // Box 3 = Box 1 + Box 2.
            if ($box3 !== $box1 + $box2) {
                $validator->errors()->add(
                    'total_vat_due_pence',
                    'Box 3 (total VAT due) must equal Box 1 + Box 2.',
                );
            }

            // Box 5 = abs(Box 3 - Box 4).
            if ($box5 !== abs($box3 - $box4)) {
                $validator->errors()->add(
                    'net_vat_due_pence',
                    'Box 5 (net VAT due) must equal the absolute difference of Box 3 and Box 4.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attestation.accepted' => 'You must confirm the digital-records attestation before submitting.',
        ];
    }

    private function safePence(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return HmrcMoney::fromInput(is_string($value) ? $value : (float) $value);
        } catch (InvalidArgumentException) {
            return -1;
        }
    }
}
