<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\InstructorStatus;
use App\Enums\PdiStatus;
use App\Enums\TransmissionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreInstructorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO: Add role-based authorization later
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', Password::default()],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string'],
            'transmission_type' => ['required', Rule::in(TransmissionType::values())],
            'status' => ['nullable', Rule::in(InstructorStatus::values())],
            'pdi_status' => ['nullable', Rule::in(PdiStatus::values())],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
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
            'name.required' => 'The instructor name is required.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'This email address is already in use.',
            'transmission_type.required' => 'Please select a transmission type.',
            'transmission_type.in' => 'Please select a valid transmission type.',
            'status.in' => 'Please select a valid status.',
            'pdi_status.in' => 'Please select a valid PDI status.',
        ];
    }
}
