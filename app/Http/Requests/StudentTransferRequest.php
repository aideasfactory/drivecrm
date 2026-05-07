<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StudentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'destination_instructor_id' => ['required', 'integer', 'exists:instructors,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Please select a student to transfer.',
            'student_id.exists' => 'Selected student could not be found.',
            'destination_instructor_id.required' => 'Please select a destination instructor.',
            'destination_instructor_id.exists' => 'Selected instructor could not be found.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $student = Student::find($this->input('student_id'));
            $destination = Instructor::find($this->input('destination_instructor_id'));

            if (! $student || ! $destination) {
                return;
            }

            if (! $student->instructor_id) {
                $validator->errors()->add(
                    'student_id',
                    'This student does not currently have an instructor — there is nothing to transfer.',
                );

                return;
            }

            if ($student->instructor_id === $destination->id) {
                $validator->errors()->add(
                    'destination_instructor_id',
                    'The destination instructor is the same as the current instructor.',
                );
            }

            if (! $destination->canReceiveTransfers()) {
                $validator->errors()->add(
                    'destination_instructor_id',
                    'Selected instructor has not completed Stripe onboarding and cannot receive payouts.',
                );
            }
        });
    }
}
