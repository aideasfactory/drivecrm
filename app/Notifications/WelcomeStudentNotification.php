<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $temporaryPassword,
        public Instructor $instructor
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
        $instructorName = $this->instructor->user?->name ?? 'your instructor';
        $appName = config('app.name');

        return (new MailMessage)
            ->subject('Welcome to '.$appName.'!')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($instructorName.' has added you as a student on '.$appName.'.')
            ->line('Your temporary login details are:')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Password:** '.$this->temporaryPassword)
            ->action('Download App Now', route('get-app'))
            ->line('Please change your password the first time you sign in.')
            ->salutation('Thanks, '.$appName);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'instructor_id' => $this->instructor->id,
        ];
    }
}
