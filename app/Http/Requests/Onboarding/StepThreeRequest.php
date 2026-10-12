<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StepThreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enquiry = $this->get('enquiry');
        $isStaffBooking = $enquiry instanceof Enquiry && $enquiry->isStaffBooking();

        // Onboarding only offers Drive packages — reject instructor-owned
        // or inactive packages outright. Learners also cannot select a
        // package that is private to pupils. Staff booking on a pupil's
        // behalf can still choose those packages.
        $packageRule = Rule::exists('packages', 'id')
            ->whereNull('instructor_id')
            ->where('active', true);

        if (! $isStaffBooking) {
            $packageRule->where('visible_to_pupils', true);
        }

        return [
            'package_id' => [
                'required',
                $packageRule,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required' => 'Please select a package to continue',
            'package_id.exists' => 'The selected package is not available',
        ];
    }
}
