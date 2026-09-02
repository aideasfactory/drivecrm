<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstructorLessonPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public LessonPayment $lessonPayment,
        public Student $student
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
        $lesson = $this->lessonPayment->lesson;
        $lessonDate = $lesson->date->format('l, F j, Y');
        $lessonTime = $lesson->start_time->format('g:i A');
        $amount = $this->lessonPayment->formatted_amount;
        $studentName = trim(($this->student->first_name ?? '').' '.($this->student->surname ?? ''));

        return $this->templatedMail(
            EmailTemplateKey::InstructorLessonPaymentReceived,
            [
                'student_name' => $studentName,
                'amount' => $amount,
                'lesson_date' => $lessonDate,
                'lesson_time' => $lessonTime,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lesson_payment_id' => $this->lessonPayment->id,
            'lesson_id' => $this->lessonPayment->lesson_id,
            'student_name' => trim(($this->student->first_name ?? '').' '.($this->student->surname ?? '')),
            'amount' => $this->lessonPayment->formatted_amount,
        ];
    }
}
