<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeleteStudentAction
{
    /**
     * Soft-delete a learner profile and lock the linked login.
     *
     * The student row is kept (soft delete) so orders, lessons, invoices,
     * and payouts that cascade on student delete are not destroyed.
     * The linked user cannot sign in or reset a password afterwards.
     */
    public function __invoke(Student $student, ?User $deletedBy = null): void
    {
        DB::transaction(function () use ($student, $deletedBy): void {
            $student->loadMissing('user');

            if ($student->profile_picture_path) {
                Storage::disk('s3')->delete($student->profile_picture_path);
            }

            $student->logActivity(
                'Student profile deleted',
                'student',
                [
                    'deleted_by_user_id' => $deletedBy?->id,
                    'had_instructor_id' => $student->instructor_id,
                ],
            );

            $student->forceFill([
                'instructor_id' => null,
                'profile_picture_path' => null,
                'status' => 'inactive',
                'inactive_reason' => 'Profile deleted by staff',
            ])->save();

            $this->lockLinkedUser($student);

            $student->delete();
        });
    }

    private function lockLinkedUser(Student $student): void
    {
        $user = $student->user;

        if (! $user) {
            return;
        }

        $user->tokens()->delete();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill([
            'email' => "deleted-student-{$student->id}@deleted.invalid",
            'email_verified_at' => null,
            'password' => Str::random(64),
            'remember_token' => null,
            'expo_push_token' => null,
        ])->save();
    }
}
