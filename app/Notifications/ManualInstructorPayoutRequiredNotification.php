<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells DRIVE staff that a lesson was signed off but the instructor was not
 * paid, because the platform Stripe balance could not cover the transfer.
 */
class ManualInstructorPayoutRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payout $payout) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payout = $this->payout->loadMissing(['lesson.order.student', 'instructor.user']);
        $lesson = $payout->lesson;
        $student = $lesson?->order?->student;
        $instructor = $payout->instructor;

        $studentName = $student
            ? trim($student->first_name.' '.$student->surname)
            : 'Unknown student';
        $instructorName = $instructor?->user?->name ?? 'Unknown instructor';
        $lessonDate = $lesson?->date?->format('l, j F Y') ?? 'Unknown date';
        $stripeError = $payout->failure_message ?: 'The Stripe balance was insufficient.';

        $message = (new MailMessage)
            ->subject('Action required: instructor payout not sent — check Stripe balance')
            ->greeting('Manual payout needed')
            ->line('A lesson was signed off, but the Stripe transfer to the instructor did not go through because the platform balance was too low.')
            ->line('The instructor was not shown this error. The lesson is complete.')
            ->line('')
            ->line("**Instructor:** {$instructorName}")
            ->line("**Student:** {$studentName}")
            ->line("**Lesson:** {$lessonDate}")
            ->line('**Amount to pay:** '.$payout->formatted_amount)
            ->line('**Payout:** #'.$payout->id)
            ->line('**Lesson id:** '.($lesson?->id ?? 'n/a'))
            ->line('')
            ->line('**Stripe error:**')
            ->line($stripeError)
            ->line('')
            ->line('Top up the Stripe balance and pay the instructor manually, then click "Mark as paid" on the payout under Payments in the admin panel. Marking it as paid does not send any money.');

        $paymentsUrl = $this->paymentsUrl($instructor?->id, $student?->id);

        if ($paymentsUrl !== null) {
            $message->action('Open Payments', $paymentsUrl);
        }

        return $message->salutation('— '.config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payout_id' => $this->payout->id,
            'lesson_id' => $this->payout->lesson_id,
            'instructor_id' => $this->payout->instructor_id,
            'failure_code' => $this->payout->failure_code,
            'failure_message' => $this->payout->failure_message,
        ];
    }

    protected function paymentsUrl(?int $instructorId, ?int $studentId): ?string
    {
        if ($instructorId === null) {
            return null;
        }

        $url = route('instructors.show', $instructorId);

        if ($studentId === null) {
            return $url.'?tab=reports';
        }

        return $url.'?tab=student&student='.$studentId.'&subtab=payments';
    }
}
