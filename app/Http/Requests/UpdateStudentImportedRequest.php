<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentImportedRequest extends FormRequest
{
    /**
     * Only owners with full admin access may mark pupils as imported — the
     * same people who can book cash orders for them.
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
            'imported' => ['required', 'boolean'],
        ];
    }
}
