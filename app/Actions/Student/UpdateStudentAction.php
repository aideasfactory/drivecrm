<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class UpdateStudentAction
{
    /**
     * Update an existing student record.
     *
     * @param  array{first_name?: string, surname?: string, email?: string|null, phone?: string|null, contact_first_name?: string|null, contact_surname?: string|null, contact_email?: string|null, contact_phone?: string|null, owns_account?: bool}  $data
     */
    public function __invoke(Student $student, array $data): Student
    {
        $student->update($data);

        $this->syncLinkedUser($student, $data);

        $student->logActivity(
            'Student profile updated',
            'profile',
            ['updated_fields' => array_keys($data)]
        );

        return $student->fresh(['user', 'instructor.user']);
    }

    /**
     * Keep the linked user account in sync with the student record.
     *
     * Invites and notifications are sent to `users.email`, so a stale user
     * record would deliver them to the old address. Name follows the creation
     * convention: `first_name . ' ' . surname`. The email is only synced when
     * a non-empty value was provided because `users.email` is NOT NULL.
     *
     * @param  array{first_name?: string, surname?: string, email?: string|null}  $data
     */
    protected function syncLinkedUser(Student $student, array $data): void
    {
        $user = $student->user;

        if (! $user) {
            return;
        }

        $attributes = [];

        if (array_key_exists('first_name', $data) || array_key_exists('surname', $data)) {
            $attributes['name'] = trim($student->first_name.' '.$student->surname);
        }

        if (! empty($data['email'])) {
            $attributes['email'] = $student->email;
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }
    }
}
