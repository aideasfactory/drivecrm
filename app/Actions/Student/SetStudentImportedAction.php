<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SetStudentImportedAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Mark an existing pupil as imported from another system (e.g. GoRoadie),
     * or clear the mark. Imported pupils can have cash payments turned on by a
     * full-access owner. Sets `users.imported_at` exactly as the importer
     * does; the welcome-email flag and existing orders are left untouched.
     * Clearing the mark also turns cash payments off, so marking the pupil
     * again later never turns cash back on by itself.
     *
     * @throws ValidationException When the pupil has no login account to mark.
     */
    public function __invoke(Student $student, bool $imported, ?User $changedBy = null): Student
    {
        $user = $student->user;

        if (! $user) {
            throw ValidationException::withMessages([
                'imported' => 'This pupil has no account, so they cannot be marked as imported.',
            ]);
        }

        if ($student->isImported() === $imported) {
            return $student;
        }

        $previousImportedAt = $user->imported_at?->toIso8601String();

        $user->forceFill(['imported_at' => $imported ? now() : null])->save();

        if (! $imported && $student->cash_payments_enabled) {
            $student->forceFill(['cash_payments_enabled' => false])->save();
        }

        $changedByName = $changedBy?->name ?? 'head office';

        ($this->logActivity)(
            $student,
            $imported
                ? "Marked as an imported pupil by {$changedByName} (cash payments can now be turned on)"
                : "Imported pupil mark removed by {$changedByName} (cash payments turned off)",
            'profile',
            [
                'imported' => $imported,
                'previous_imported_at' => $previousImportedAt,
                'changed_by_user_id' => $changedBy?->id,
            ],
        );

        return $student->refresh();
    }
}
