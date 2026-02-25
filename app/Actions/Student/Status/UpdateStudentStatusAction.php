<?php

declare(strict_types=1);

namespace App\Actions\Student\Status;

use App\Models\Student;

class UpdateStudentStatusAction
{
    /**
     * Update a student's status and log the change.
     *
     * @param  array{status: string, inactive_reason?: string|null}  $data
     */
    public function __invoke(Student $student, array $data): Student
    {
        $previousStatus = $student->status ?? 'active';
        $newStatus = $data['status'];

        $student->update([
            'status' => $newStatus,
            'inactive_reason' => $data['inactive_reason'] ?? null,
        ]);

        $student->logActivity(
            "Status changed from {$previousStatus} to {$newStatus}",
            'profile',
            [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $data['inactive_reason'] ?? null,
            ]
        );

        return $student->fresh();
    }
}
