<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
     * Instructors must provide a recipient_id (the student ID, or the support
     * user ID with recipient_type "support"). Students may omit it — the
     * backend resolves their assigned instructor.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $recipientRule = $this->user()->isStudent()
            ? ['required_with:recipient_type', 'integer']
            : ['required', 'integer'];

        return [
            'recipient_id' => $recipientRule,
            'recipient_type' => ['sometimes', 'string', 'in:student,support'],
            'message' => ['required', 'string', 'max:5000'],
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
            'recipient_id.required' => 'A recipient is required.',
            'recipient_id.required_with' => 'A recipient is required.',
            'recipient_type.in' => 'The recipient type must be student or support.',
            'recipient_id.exists' => 'The selected recipient does not exist.',
            'message.required' => 'A message is required.',
            'message.max' => 'The message must not exceed 5000 characters.',
        ];
    }
}
