<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HmrcReconnectSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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

        return $this->templatedMail(
            EmailTemplateKey::InstructorHmrcReconnect,
            [
                'recipient_name' => $notifiable->name,
                'when' => $when,
                'app_name' => config('app.name'),
            ],
            url('/hmrc'),
        );
    }
}
