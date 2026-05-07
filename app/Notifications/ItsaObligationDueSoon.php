<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\HmrcItsaObligation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ItsaObligationDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HmrcItsaObligation $obligation,
        public int $daysUntilDue,
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
        $days = $this->daysUntilDue;
        $when = match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };
        $period = $this->obligation->period_start_date->format('j M Y')
            .' – '
            .$this->obligation->period_end_date->format('j M Y');

        return (new MailMessage)
            ->subject("MTD ITSA quarterly update due {$when}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your quarterly self-employment update for {$period} is due {$when}.")
            ->action('Open ITSA submissions', url('/hmrc/itsa'))
            ->line('Filing on time keeps you compliant with HMRC and avoids automatic penalties.');
    }
}
