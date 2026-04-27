<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\UserRole;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateUserAndStudentFromEnquiryAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create or retrieve user and student from enquiry data.
     *
     * @return array{user: User, student: Student, is_new_user: bool, temporary_password: string|null}
     *
     * @throws \Exception
     */
    public function execute(Enquiry $enquiry): array
    {
        try {
            DB::beginTransaction();

            $step1 = $enquiry->getStepData(1) ?? [];
            $step2 = $enquiry->getStepData(2) ?? [];
            $step5 = $enquiry->getStepData(5) ?? [];

            // Determine email based on booking_for_someone_else flag
            $bookingForSomeoneElse = $step5['booking_for_someone_else'] ?? false;

            if ($bookingForSomeoneElse) {
                // Learner is the user (student) - get from step5
                $userEmail = $step5['learner_email'] ?? null;
                $userName = trim(($step5['learner_first_name'] ?? '').' '.($step5['learner_last_name'] ?? ''));
            } else {
                // Contact is the user (student) - get from step1
                $userEmail = $step1['email'] ?? null;
                $userName = trim(($step1['first_name'] ?? '').' '.($step1['last_name'] ?? ''));
            }

            if (! $userEmail) {
                throw new \Exception('User email is required');
            }

            // Check if user already exists
            $user = User::where('email', $userEmail)->first();
            $isNewUser = false;
            $temporaryPassword = null;

            if (! $user) {
                // Create new user with a readable temporary password
                $temporaryPassword = Str::random(12);

                $user = User::create([
                    'name' => $userName,
                    'email' => $userEmail,
                    'password' => Hash::make($temporaryPassword),
                    'password_change_required' => true,
                    'role' => UserRole::STUDENT,
                    'email_verified_at' => null, // Require verification later
                ]);

                $isNewUser = true;

                Log::info('Created new user from onboarding', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'enquiry_id' => $enquiry->id,
                ]);
            } else {
                Log::info('Reusing existing user for onboarding', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'enquiry_id' => $enquiry->id,
                ]);
            }

            // Create Stripe customer if doesn't exist
            if (! $user->stripe_customer_id) {
                $customerResult = $this->stripeService->createOrGetCustomer($user);

                if (! $customerResult['success']) {
                    throw new \Exception('Failed to create Stripe customer: '.($customerResult['error'] ?? 'Unknown error'));
                }

                $user->stripe_customer_id = $customerResult['customer_id'];
                $user->save();

                Log::info('Created Stripe customer for onboarding user', [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_customer_id,
                ]);
            }

            // Get or update student record — updateOrCreate ensures returning
            // users get their details refreshed with the latest onboarding data
            $studentData = $this->getStudentData($enquiry, $step1, $step2, $step5, $bookingForSomeoneElse);
            $student = Student::updateOrCreate(
                ['user_id' => $user->id],
                $studentData
            );

            if (! $student->wasRecentlyCreated) {
                Log::info('Updated existing student record from onboarding', [
                    'student_id' => $student->id,
                    'user_id' => $user->id,
                    'enquiry_id' => $enquiry->id,
                ]);
            }

            DB::commit();

            Log::info('Successfully created/retrieved user and student from enquiry', [
                'user_id' => $user->id,
                'student_id' => $student->id,
                'enquiry_id' => $enquiry->id,
                'is_new_user' => $isNewUser,
                'is_new_student' => $student->wasRecentlyCreated,
            ]);

            return [
                'user' => $user,
                'student' => $student,
                'is_new_user' => $isNewUser,
                'temporary_password' => $temporaryPassword,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create user and student from enquiry', [
                'enquiry_id' => $enquiry->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Build student data array based on booking context.
     */
    protected function getStudentData(Enquiry $enquiry, array $step1, array $step2, array $step5, bool $bookingForSomeoneElse): array
    {
        $instructorId = $step2['instructor_id'] ?? null;

        if ($bookingForSomeoneElse) {
            // Learner is the student, contact (from step1) is the booker
            return [
                'instructor_id' => $instructorId,
                'first_name' => $step5['learner_first_name'] ?? null,
                'surname' => $step5['learner_last_name'] ?? null,
                'email' => $step5['learner_email'] ?? null,
                'phone' => $step5['learner_phone'] ?? null,
                'contact_first_name' => $step1['first_name'] ?? null,
                'contact_surname' => $step1['last_name'] ?? null,
                'contact_email' => $step1['email'] ?? null,
                'contact_phone' => $step1['phone'] ?? null,
                'terms_accepted' => $step5['learner_terms'] ?? false,
                'allow_communications' => $step5['learner_communications'] ?? false,
                'contact_terms' => $step1['privacy_consent'] ?? false,
                'contact_communications' => $step1['privacy_consent'] ?? false,
                'owns_account' => false, // Contact is booking for learner
            ];
        }

        // Contact (from step1) is the student (booking for self)
        return [
            'instructor_id' => $instructorId,
            'first_name' => $step1['first_name'] ?? null,
            'surname' => $step1['last_name'] ?? null,
            'email' => $step1['email'] ?? null,
            'phone' => $step1['phone'] ?? null,
            'contact_first_name' => null,
            'contact_surname' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'terms_accepted' => $step1['privacy_consent'] ?? false,
            'allow_communications' => $step1['privacy_consent'] ?? false,
            'contact_terms' => null,
            'contact_communications' => null,
            'owns_account' => true, // Student owns their own account
        ];
    }
}
