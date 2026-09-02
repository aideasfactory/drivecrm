<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\HmrcVatObligation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VatObligationDueSoon extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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

        return $this->templatedMail(
            EmailTemplateKey::InstructorVatDueSoon,
            [
                'recipient_name' => $notifiable->name,
                'when' => $when,
                'period' => $period,
            ],
            url('/hmrc/vat'),
        );
    }
}
