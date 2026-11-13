<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\SetStudentCashPaymentsAction;
use App\Enums\UserRole;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Support\ImportValues;
use App\Support\PlaceholderEmails;

class ImportStudentAction
{
    public function __construct(
        protected SetStudentCashPaymentsAction $setStudentCashPayments,
    ) {}

    /**
     * Create the student for a students.csv row, assigned to the instructor.
     * Phone numbers are normalised (ImportValues::ukPhone) and alternative
     * payments are turned on, logged as changed by whoever started the run.
     *
     * No email is sent: `imported_at` is set and `welcome_email_pending = true`.
     * `import:send-welcome-emails` later issues a temporary password and sends
     * the usual student welcome email.
     *
     * A student already imported (ref mapped) is returned untouched. An email
     * that already belongs to a Drive+ pupil is linked to that pupil (mapping
     * `linked`) so their new diary lessons can be added — the account itself
     * is not changed. An invalid email, or one on an account that isn't a
     * pupil (e.g. an instructor), is skipped (student null).
     *
     * A blank or legacy-generated email (e.g. `example@<id>-student.com`) gets
     * a placeholder (`no-email+<ref>@drive-plus.co.uk`) on both the user and
     * the student, and the user is flagged `exclude_from_emails`.
     *
     * @param  array<string, string|int>  $row
     * @return array{student: Student|null, created: bool, linked: bool}
     */
    public function __invoke(Instructor $instructor, array $row, ImportRun $run): array
    {
        $ref = (string) $row['student_ref'];

        $mapping = ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_STUDENT)
            ->where('source_ref', $ref)
            ->first(['model_id', 'action']);

        if ($mapping !== null) {
            // A pupil deleted in Drive since it was imported stays deleted: skipped (student null), never recreated.
            // A ref linked by email stays linked on every re-run, so its row never fills or ticks the pupil it was linked to.
            return [
                'student' => Student::query()->find($mapping->model_id),
                'created' => false,
                'linked' => $mapping->action === ImportMapping::ACTION_LINKED,
            ];
        }

        $email = strtolower(trim((string) $row['email']));
        $isPlaceholder = $email === '' || PlaceholderEmails::isPlaceholder($email);

        // No real email: a stand-in address built from the ref; never emailed.
        if ($isPlaceholder) {
            $email = PlaceholderEmails::forRef($ref);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['student' => null, 'created' => false, 'linked' => false];
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $existingStudent = Student::query()->where('user_id', $existingUser->id)->first();

            if (! $existingStudent) {
                return ['student' => null, 'created' => false, 'linked' => false];
            }

            ImportMapping::record($run, ImportMapping::ENTITY_STUDENT, $ref, $existingStudent->id, ImportMapping::ACTION_LINKED);

            return ['student' => $existingStudent, 'created' => false, 'linked' => true];
        }

        $firstName = (string) $row['first_name'];
        $surname = (string) $row['surname'];
        $status = $this->nullable($row, 'status') ?? 'active';

        $user = User::create([
            'name' => "{$firstName} {$surname}",
            'email' => $email,
            'password' => ImportValues::placeholderPasswordHash(),
            'password_change_required' => true,
            'welcome_email_pending' => true,
            'exclude_from_emails' => $isPlaceholder,
            'imported_at' => now(),
            'role' => UserRole::STUDENT,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'instructor_id' => $instructor->id,
            'first_name' => $firstName,
            'surname' => $surname,
            'email' => $email,
            'phone' => ImportValues::ukPhone($this->nullable($row, 'phone')),
            'contact_first_name' => $this->nullable($row, 'contact_first_name'),
            'contact_surname' => $this->nullable($row, 'contact_surname'),
            'contact_email' => $this->nullable($row, 'contact_email'),
            'contact_phone' => ImportValues::ukPhone($this->nullable($row, 'contact_phone')),
            'owns_account' => true,
            'status' => $status,
            'inactive_reason' => $status === 'active' ? null : $this->nullable($row, 'inactive_reason'),
        ]);

        ($this->setStudentCashPayments)($student, true, $run->user);

        ImportMapping::record($run, ImportMapping::ENTITY_STUDENT, $ref, $student->id);

        return ['student' => $student, 'created' => true, 'linked' => false];
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
