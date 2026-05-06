<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\HmrcVatObligation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VatObligationDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HmrcVatObligation $obligation,
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
            ->subject("MTD VAT return due {$when}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your VAT return for {$period} is due {$when}.")
            ->action('Open VAT submissions', url('/hmrc/vat'))
            ->line('VAT submissions are final once filed — corrections must be made in a future period.');
    }
}
