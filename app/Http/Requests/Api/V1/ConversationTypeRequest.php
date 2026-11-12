<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ConversationTypeRequest extends FormRequest
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
     * The optional type says which kind of chat the conversation id refers
     * to: "student" (an instructor's student id), "support" (an owner's
     * user id) or "instructor" (an instructor's user id, for pupils). These
     * match chat_type on conversation rows. Omitted keeps the legacy
     * student-first resolution.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:student,support,instructor'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The chat type must be student, support or instructor.',
        ];
    }
}
