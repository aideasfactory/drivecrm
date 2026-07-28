<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AccountDeletionRequestStatus;
use App\Enums\InstructorStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessAccountDeletionAction
{
    /**
     * Hard-process a due deletion request.
     *
     * Anonymises rather than deletes rows: `users.id` cascades would wipe
     * lesson/payment history that the other party (instructor or CRM owner)
     * still needs for their own records. Personal data is scrubbed, all
     * Sanctum tokens are revoked, and an instructor's students are detached
     * (`students.instructor_id = null` — the existing soft-remove convention).
     */
    public function __invoke(AccountDeletionRequest $deletionRequest): void
    {
        DB::transaction(function () use ($deletionRequest): void {
            $user = $deletionRequest->user()->with(['instructor', 'student'])->first();

            if ($user) {
                $user->tokens()->delete();

                if ($user->instructor) {
                    $this->anonymiseInstructor($user->instructor);
                }

                if ($user->student) {
                    $this->anonymiseStudent($user->student);
                }

                $this->anonymiseUser($user);
            }

            $deletionRequest->update([
                'status' => AccountDeletionRequestStatus::COMPLETED,
                'completed_at' => now(),
            ]);
        });
    }

    private function anonymiseUser(User $user): void
    {
        $user->forceFill([
            'name' => 'Deleted User',
            'email' => "deleted-{$user->id}@deleted.invalid",
            'email_verified_at' => null,
            'password' => Str::random(64),
            'remember_token' => null,
            'expo_push_token' => null,
        ])->save();
    }

    private function anonymiseInstructor(Instructor $instructor): void
    {
        Student::where('instructor_id', $instructor->id)->update(['instructor_id' => null]);

        $instructor->forceFill([
            'bio' => null,
            'phone' => null,
            'address' => null,
            'postcode' => null,
            'latitude' => null,
            'longitude' => null,
            'pin' => null,
            'profile_picture_path' => null,
            'nino' => null,
            'utr' => null,
            'vrn' => null,
            'companies_house_number' => null,
            'status' => InstructorStatus::Archived->value,
        ])->save();
    }

    private function anonymiseStudent(Student $student): void
    {
        $student->forceFill([
            'instructor_id' => null,
            'first_name' => 'Deleted',
            'surname' => 'User',
            'email' => null,
            'phone' => null,
            'contact_first_name' => null,
            'contact_surname' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'profile_picture_path' => null,
            'status' => 'inactive',
            'inactive_reason' => 'Account deleted at user request',
        ])->save();
    }
}
