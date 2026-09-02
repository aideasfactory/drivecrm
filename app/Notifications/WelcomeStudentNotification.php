<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public string $temporaryPassword,
        public Instructor $instructor
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
        return $this->templatedMail(
            EmailTemplateKey::LearnerWelcome,
            [
                'recipient_name' => $notifiable->name,
                'instructor_name' => $this->instructor->user?->name ?? 'your instructor',
                'app_name' => config('app.name'),
                'email' => $notifiable->email,
                'temporary_password' => $this->temporaryPassword,
            ],
            route('get-app'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'instructor_id' => $this->instructor->id,
        ];
    }
}
