<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class DeleteStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        if (! $student instanceof Student) {
            return false;
        }

        return $this->user()?->can('deleteProfile', $student) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
