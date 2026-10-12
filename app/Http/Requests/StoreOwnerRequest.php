<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OwnerAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerRequest extends FormRequest
{
    /**
     * Only owners with full access may add new admins.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasFullOwnerAccess();
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'owner_access' => ['required', 'string', Rule::enum(OwnerAccess::class)],
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
            'name.required' => 'A name is required.',
            'email.required' => 'An email address is required.',
            'email.unique' => 'A user with this email address already exists.',
            'password.required' => 'A temporary password is required.',
            'password.min' => 'The temporary password must be at least 8 characters.',
            'owner_access.required' => 'An access level is required.',
            'owner_access.enum' => 'The selected access level is not valid.',
        ];
    }

    public function ownerAccess(): OwnerAccess
    {
        return OwnerAccess::from($this->validated('owner_access'));
    }
}
