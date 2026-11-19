<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Instructor;
use Illuminate\Contracts\Validation\Validator;

class InstructorMergeRequest extends InstructorMergePreviewRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'confirm_email' => ['required', 'string', 'max:255'],
            'warnings_acknowledged' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'confirm_email.required' => "Type the old account's email to confirm the merge.",
        ];
    }

    /**
     * The typed confirmation must match the old account's current login email.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = Instructor::with('user:id,email')->find($this->integer('from_instructor_id'));
            $typed = mb_strtolower(trim((string) $this->input('confirm_email')));

            if ($from?->user === null || $typed !== mb_strtolower($from->user->email)) {
                $validator->errors()->add('confirm_email', "The email you typed doesn't match the old account's email.");
            }
        });
    }
}
