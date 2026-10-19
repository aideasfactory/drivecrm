<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Models\Student;
use App\Models\User;

class SetStudentCashPaymentsAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Turn cash payments on or off for a pupil. Off by default (the importer
     * never turns it on). Existing orders are left untouched.
     */
    public function __invoke(Student $student, bool $enabled, ?User $changedBy = null): Student
    {
        if ($student->cash_payments_enabled === $enabled) {
            return $student;
        }

        $student->forceFill(['cash_payments_enabled' => $enabled])->save();

        $changedByName = $changedBy?->name ?? 'head office';

        ($this->logActivity)(
            $student,
            $enabled
                ? "Cash payments turned on by {$changedByName}"
                : "Cash payments turned off by {$changedByName}",
            'profile',
            [
                'cash_payments_enabled' => $enabled,
                'changed_by_user_id' => $changedBy?->id,
            ],
        );

        return $student->refresh();
    }
}
