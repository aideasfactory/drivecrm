<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use App\Models\Student;
use App\Support\BookingPayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonsBankedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    /**
     * @param  array<int, string>  $originalSlots  Each lesson as it was booked (the lessons no longer have a date).
     * @param  string  $reason  The reason label plus any details.
     * @param  string  $rebookBy  Formatted date after which unrebooked lessons are cancelled and refunded.
     */
    public function __construct(
        public Student $student,
        public ?Instructor $instructor,
        public array $originalSlots,
        public string $packageName,
        public string $reason,
        public string $rebookBy,
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
        $count = count($this->originalSlots);

        return $this->templatedMail(
            EmailTemplateKey::LearnerLessonsBanked,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor?->user?->name ?? 'your instructor',
                'lesson_count' => $count,
                'lesson_list' => collect($this->originalSlots)->map(fn (string $slot): string => '• '.$slot)->implode("\n"),
                'package_name' => $this->packageName,
                'reason' => $this->reason,
                'rebook_by' => $this->rebookBy,
                'expiry_months' => BookingPayments::bankedLessonExpiryMonths(),
                'app_name' => config('app.name'),
                'multiple_lessons' => $count !== 1,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor?->id,
            'original_slots' => $this->originalSlots,
            'reason' => $this->reason,
        ];
    }
}
