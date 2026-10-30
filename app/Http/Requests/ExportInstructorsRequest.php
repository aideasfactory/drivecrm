<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Instructor\GetInstructorListAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportInstructorsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'setup' => ['nullable', Rule::in([
                GetInstructorListAction::SETUP_COMPLETE,
                GetInstructorListAction::SETUP_INCOMPLETE,
            ])],
            'onboarding_email' => ['nullable', Rule::in([
                GetInstructorListAction::ONBOARDING_EMAIL_SENT,
                GetInstructorListAction::ONBOARDING_EMAIL_NOT_SENT,
            ])],
            'stripe' => ['nullable', Rule::in([
                GetInstructorListAction::STRIPE_CONNECTED,
                GetInstructorListAction::STRIPE_NOT_CONNECTED,
            ])],
            'availability' => ['nullable', Rule::in([
                GetInstructorListAction::AVAILABILITY_ADDED,
                GetInstructorListAction::AVAILABILITY_NOT_ADDED,
            ])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'setup.in' => 'The setup filter must be complete or incomplete.',
            'onboarding_email.in' => 'The onboarding email filter must be sent or not_sent.',
            'stripe.in' => 'The Stripe filter must be connected or not_connected.',
            'availability.in' => 'The availability filter must be added or not_added.',
        ];
    }
}
