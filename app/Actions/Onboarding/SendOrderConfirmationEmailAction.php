<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Shared\LogActivityAction;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use App\Notifications\WelcomeStudentNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendOrderConfirmationEmailAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Send order confirmation email to the appropriate recipient.
     *
     * Sends to:
     * - contact_email if owns_account = false (booking for someone else)
     * - email if owns_account = true (booking for self)
     */
    public function execute(Order $order, Student $student): void
    {
        try {
            $isBookedByContact = ! $student->owns_account;

            // Determine recipient email
            if ($isBookedByContact) {
                // Send to contact person
                $recipientEmail = $student->contact_email;
                $recipientName = trim(($student->contact_first_name ?? '').' '.($student->contact_surname ?? ''));
            } else {
                // Send to learner (student)
                $recipientEmail = $student->email;
                $recipientName = trim(($student->first_name ?? '').' '.($student->surname ?? ''));
            }

            if (! $recipientEmail) {
                Log::warning('Cannot send order confirmation: recipient email is missing', [
                    'order_id' => $order->id,
                    'student_id' => $student->id,
                    'owns_account' => $student->owns_account,
                ]);

                return;
            }

            // Send on-demand notification (serializable for queues)
            Notification::route('mail', $recipientEmail)
                ->notify(new OrderConfirmationNotification($order, $student, $isBookedByContact));

            Log::info('Order confirmation email queued', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'recipient_email' => $recipientEmail,
                'is_booked_by_contact' => $isBookedByContact,
            ]);

            // Log notification activity for the student
            ($this->logActivity)(
                $student,
                "Booking confirmation email sent to {$recipientEmail}",
                'notification',
                [
                    'type' => 'order_confirmation',
                    'order_id' => $order->id,
                    'recipient_email' => $recipientEmail,
                    'is_booked_by_contact' => $isBookedByContact,
                ]
            );

            // Log notification activity for the instructor
            if ($order->instructor) {
                ($this->logActivity)(
                    $order->instructor,
                    "Booking confirmation email sent to {$recipientName} ({$recipientEmail})",
                    'notification',
                    [
                        'type' => 'order_confirmation',
                        'order_id' => $order->id,
                        'student_id' => $student->id,
                        'recipient_email' => $recipientEmail,
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - email failure shouldn't break the checkout flow
        }

        $this->sendWelcomeEmailIfPending($order, $student);
    }

    /**
     * Send the temporary-password welcome email to a pupil who was newly created
     * during web onboarding. No-op for returning users (flag never set) and for
     * users where another concurrent caller has already claimed the dispatch.
     */
    protected function sendWelcomeEmailIfPending(Order $order, Student $student): void
    {
        try {
            $user = $student->user;

            if (! $user || ! $user->welcome_email_pending) {
                return;
            }

            $instructor = $order->instructor;

            if (! $instructor) {
                Log::warning('Cannot send pupil welcome email: order has no instructor', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                ]);

                return;
            }

            $temporaryPassword = Str::random(12);

            $claimed = User::where('id', $user->id)
                ->where('welcome_email_pending', true)
                ->update([
                    'password' => Hash::make($temporaryPassword),
                    'welcome_email_pending' => false,
                ]);

            if ($claimed === 0) {
                return;
            }

            $user->notify(new WelcomeStudentNotification($temporaryPassword, $instructor));

            Log::info('Pupil welcome email queued', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'student_id' => $student->id,
                'recipient_email' => $user->email,
            ]);

            ($this->logActivity)(
                $student,
                "Welcome email sent to {$user->email}",
                'notification',
                [
                    'type' => 'welcome_student',
                    'recipient_email' => $user->email,
                    'order_id' => $order->id,
                    'instructor_id' => $instructor->id,
                ]
            );

            ($this->logActivity)(
                $instructor,
                "Welcome email sent to new student {$student->first_name} {$student->surname} ({$user->email})",
                'notification',
                [
                    'type' => 'welcome_student',
                    'recipient_email' => $user->email,
                    'order_id' => $order->id,
                    'student_id' => $student->id,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send pupil welcome email', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
