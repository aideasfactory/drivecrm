<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class CalendarClashDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    /**
     * @param  CalendarItem  $newItem  The newly created calendar item
     * @param  Collection<int, CalendarItem>  $clashingItems  Existing items that clash
     * @param  Instructor  $instructor  The instructor who owns the calendar
     */
    public function __construct(
        public CalendarItem $newItem,
        public Collection $clashingItems,
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
        $date = $this->newItem->calendar?->date?->format('l, j F Y') ?? 'N/A';
        $newTime = $this->newItem->start_time.' - '.$this->newItem->end_time;

        $clashList = $this->clashingItems
            ->map(function (CalendarItem $clash): string {
                $clashTime = $clash->start_time.' - '.$clash->end_time;
                $studentName = $this->getStudentName($clash);
                $status = $clash->status?->value ?? 'available';

                if ($studentName) {
                    return "**Clashes with:** {$clashTime} — {$studentName} ({$status})";
                }

                return "**Clashes with:** {$clashTime} — {$status} slot";
            })
            ->implode("\n");

        return $this->templatedMail(
            EmailTemplateKey::InstructorCalendarClash,
            [
                'recipient_name' => $this->instructor->user?->name ?? 'there',
                'date' => $date,
                'new_item' => $newTime,
                'clash_list' => $clashList,
                'app_name' => config('app.name'),
            ],
            url('/instructors/'.$this->instructor->id),
        );
    }

    protected function getStudentName(CalendarItem $item): ?string
    {
        $lesson = $item->lessons->first();

        if (! $lesson || ! $lesson->order || ! $lesson->order->student) {
            return null;
        }

        $student = $lesson->order->student;

        return trim(($student->first_name ?? '').' '.($student->surname ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'new_item_id' => $this->newItem->id,
            'clashing_item_ids' => $this->clashingItems->pluck('id')->toArray(),
            'instructor_id' => $this->instructor->id,
        ];
    }
}
