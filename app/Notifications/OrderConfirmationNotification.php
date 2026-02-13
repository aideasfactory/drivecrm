<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Order $order,
        public Student $student,
        public bool $isBookedByContact
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $instructor = $order->instructor;
        $firstLesson = $order->lessons()->orderBy('date')->first();

        $message = (new MailMessage)
            ->subject('Your Driving Lessons Have Been Booked!')
            ->greeting($this->getGreeting())
            ->line($this->getIntroLine())
            ->line('**Order Details:**')
            ->line("Package: {$order->package_name}")
            ->line("Number of lessons: {$order->package_lessons_count}")
            ->line("Instructor: {$instructor->user->name}");

        if ($firstLesson) {
            $message->line('First lesson: '.\Carbon\Carbon::parse($firstLesson->date)->format('l, F j, Y'));
        }

        if ($order->isUpfront()) {
            $message->line('Payment: Paid in full (£'.number_format($order->package_total_price_pence / 100, 2).')');
        } else {
            $message->line('Payment: Weekly (£'.number_format($order->package_lesson_price_pence / 100, 2).' per lesson)');
            $message->line('You will receive invoice emails 24 hours before each lesson.');
        }

        $message->line('')
            ->line('**Next Steps:**')
            ->line('1. You can log in to your account to view your lesson schedule')
            ->line('2. Your instructor will contact you to confirm the details')
            ->line('3. Make sure to arrive 5 minutes early for your first lesson');

        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;
            $message->line('')
                ->line("This booking was made for: **{$learnerName}**");
        }

        $message->action('View My Lessons', url('/student/orders/'.$this->order->id))
            ->line('Thank you for choosing us for your driving lessons!')
            ->salutation('Safe driving,
The Driving School Team');

        return $message;
    }

    /**
     * Get the appropriate greeting based on booking context.
     */
    protected function getGreeting(): string
    {
        if ($this->isBookedByContact) {
            // Email to contact person
            $contactName = $this->student->contact_first_name ?? 'there';

            return "Hello {$contactName}!";
        }

        // Email to learner
        $learnerName = $this->student->first_name ?? 'there';

        return "Hello {$learnerName}!";
    }

    /**
     * Get the appropriate intro line based on booking context.
     */
    protected function getIntroLine(): string
    {
        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;

            return "Great news! You have successfully booked driving lessons for **{$learnerName}**.";
        }

        return 'Great news! Your driving lessons have been successfully booked.';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'package_name' => $this->order->package_name,
            'lessons_count' => $this->order->package_lessons_count,
        ];
    }
}
