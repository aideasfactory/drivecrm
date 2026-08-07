<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $emailRules = ['sometimes', 'nullable', 'string', 'email', 'max:255'];

        /*
         * The student's email is synced to the linked user account, and
         * users.email is unique — reject an email already taken by another
         * user before the sync hits the database constraint.
         */
        $linkedUserId = Student::whereKey($this->route('student'))->value('user_id');

        if ($linkedUserId !== null) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($linkedUserId);
        }

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'owns_account' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'on_hold', 'passed', 'failed', 'completed'])],
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
            'email.unique' => 'This email address is already in use by another account.',
        ];
    }
}
