<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OwnerAccess;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOwnerAccessRequest extends FormRequest
{
    /**
     * Only owners with full access may change owner access levels.
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
            'owner_access' => ['required', 'string', Rule::enum(OwnerAccess::class)],
        ];
    }

    /**
     * Prevent owners from changing their own access, so nobody can lock
     * themselves out of the Owner Access page.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User|null $target */
                $target = $this->route('user');

                if ($target && $target->is($this->user())) {
                    $validator->errors()->add('owner_access', 'You cannot change your own owner access.');
                }
            },
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
            'owner_access.required' => 'An access level is required.',
            'owner_access.enum' => 'The selected access level is not valid.',
        ];
    }

    public function ownerAccess(): OwnerAccess
    {
        return OwnerAccess::from($this->validated('owner_access'));
    }
}
