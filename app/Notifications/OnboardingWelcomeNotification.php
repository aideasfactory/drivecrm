<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $temporaryPassword
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject('Welcome to '.$appName.' — Your Login Details')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Thank you for completing your booking with '.$appName.'. Your account has been created and you can now log in to the app.')
            ->line('Your temporary login details are:')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Password:** '.$this->temporaryPassword)
            ->action('Log In Now', url('/login'))
            ->line('Please change your password after your first login.')
            ->salutation('Thanks, '.$appName);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
