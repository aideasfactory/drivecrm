<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Student\ListStudentsAction;
use Illuminate\Foundation\Http\FormRequest;

class FilterStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $assignment = $this->query('assignment', 'all');

        $this->merge([
            'assignment' => in_array($assignment, ListStudentsAction::ASSIGNMENT_FILTERS, true) ? $assignment : 'all',
            'q' => $this->filled('q') ? trim((string) $this->query('q')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'assignment' => ['nullable', 'in:'.implode(',', ListStudentsAction::ASSIGNMENT_FILTERS)],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{assignment: string, q: ?string}
     */
    public function filters(): array
    {
        return [
            'assignment' => $this->validated('assignment') ?? 'all',
            'q' => filled($this->validated('q')) ? $this->validated('q') : null,
        ];
    }
}
