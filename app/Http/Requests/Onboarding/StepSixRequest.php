<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\PaymentMode;
use App\Models\Enquiry;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StepSixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['required', 'string', 'in:upfront,weekly'],
            'test_pass_guarantee' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_mode') !== PaymentMode::WEEKLY->value) {
                    return;
                }

                $enquiry = $this->get('enquiry');
                $packageId = $enquiry instanceof Enquiry ? ($enquiry->getStepData(3)['package_id'] ?? null) : null;
                $package = $packageId ? Package::find($packageId) : null;

                if ($package && ! $package->allowsWeeklyPayment()) {
                    $validator->errors()->add('payment_mode', 'Single lesson bookings must be paid in full.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'test_pass_guarantee.boolean' => 'Please choose whether to add Pass Your Test Guarantee.',
        ];
    }
}
