<?php

declare(strict_types=1);

namespace App\Notifications;

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
        $instructorName = $this->instructor->user?->name ?? 'there';

        $message = (new MailMessage)
            ->subject('Scheduling Clash Detected — '.$date)
            ->greeting("Hello {$instructorName}!")
            ->line("A scheduling clash has been detected on your calendar for **{$date}**.")
            ->line("**New item:** {$newTime}");

        foreach ($this->clashingItems as $clash) {
            $clashTime = $clash->start_time.' - '.$clash->end_time;
            $studentName = $this->getStudentName($clash);
            $status = $clash->status?->value ?? 'available';

            if ($studentName) {
                $message->line("**Clashes with:** {$clashTime} — {$studentName} ({$status})");
            } else {
                $message->line("**Clashes with:** {$clashTime} — {$status} slot");
            }
        }

        $message->line('')
            ->line('Please review your calendar and reschedule any affected lessons.')
            ->action('View Calendar', url('/instructors/'.$this->instructor->id))
            ->salutation("Thanks,\nThe ".config('app.name').' Team');

        return $message;
    }

    /**
     * Get the student name from a clashing calendar item's lessons.
     */
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
