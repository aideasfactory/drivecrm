<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\UserRole;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportStudentAction
{
    /**
     * Create the student for a students.csv row, assigned to the instructor.
     *
     * No email is sent: `imported_at` is set and `welcome_email_pending = true`.
     * `import:send-welcome-emails` later issues a temporary password and sends
     * the usual student welcome email.
     *
     * @param  array<string, string|int>  $row
     * @return array{student: Student, created: bool}
     */
    public function __invoke(Instructor $instructor, array $row, ImportRun $run): array
    {
        $ref = (string) $row['student_ref'];

        $mappedId = ImportMapping::modelIdFor(ImportMapping::ENTITY_STUDENT, $ref);
        if ($mappedId !== null) {
            return ['student' => Student::query()->findOrFail($mappedId), 'created' => false];
        }

        $email = strtolower((string) $row['email']);
        $firstName = (string) $row['first_name'];
        $surname = (string) $row['surname'];
        $status = $this->nullable($row, 'status') ?? 'active';

        $user = User::create([
            'name' => "{$firstName} {$surname}",
            'email' => $email,
            'password' => Hash::make(Str::random(48)),
            'password_change_required' => true,
            'welcome_email_pending' => true,
            'imported_at' => now(),
            'role' => UserRole::STUDENT,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'instructor_id' => $instructor->id,
            'first_name' => $firstName,
            'surname' => $surname,
            'email' => $email,
            'phone' => $this->nullable($row, 'phone'),
            'contact_first_name' => $this->nullable($row, 'contact_first_name'),
            'contact_surname' => $this->nullable($row, 'contact_surname'),
            'contact_email' => $this->nullable($row, 'contact_email'),
            'contact_phone' => $this->nullable($row, 'contact_phone'),
            'owns_account' => true,
            'status' => $status,
            'inactive_reason' => $status === 'active' ? null : $this->nullable($row, 'inactive_reason'),
        ]);

        ImportMapping::record($run, ImportMapping::ENTITY_STUDENT, $ref, $student->id);

        return ['student' => $student, 'created' => true];
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function nullable(array $row, string $field): ?string
    {
        $value = trim((string) ($row[$field] ?? ''));

        return $value === '' ? null : $value;
    }
}
