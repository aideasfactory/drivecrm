<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HmrcReconnectSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $daysUntilExpiry) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->daysUntilExpiry;
        $when = $days === 1 ? 'tomorrow' : "in {$days} days";

        return (new MailMessage)
            ->subject("HMRC connection needs reconnecting {$when}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your HMRC connection will need to be renewed {$when}.")
            ->line('You can keep filing as usual until then. After that, you will need to sign in to HMRC again so we can keep submitting on your behalf.')
            ->action('Renew HMRC connection', url('/hmrc'))
            ->line('Thank you for using '.config('app.name').'.');
    }
}
