<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReminderType;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetPaymentRemindersDueAction
{
    /**
     * Find lessons due a 48-hour payment reminder: the lesson payment is still
     * DUE, the lesson is not cancelled/completed, now is within
     * [start − 48h, start), and no payment_due_48h reminder row exists yet.
     *
     * The DB filters to a 2-day date window (index-friendly); the precise 48h
     * datetime boundary is then applied in PHP against the combined date+time.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(): Collection
    {
        $now = CarbonImmutable::now();

        return Lesson::query()
            ->whereHas('lessonPayment', function ($query): void {
                $query->where('status', PaymentStatus::DUE->value);
            })
            ->whereNotIn('status', [
                LessonStatus::CANCELLED->value,
                LessonStatus::COMPLETED->value,
            ])
            ->whereDoesntHave('reminders', function ($query): void {
                $query->where('type', ReminderType::PAYMENT_DUE_48H->value);
            })
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereDate('date', '>=', $now->toDateString())
            ->whereDate('date', '<=', $now->addDays(2)->toDateString())
            ->with(['order.student.user', 'lessonPayment'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (Lesson $lesson) use ($now): bool {
                $start = CarbonImmutable::parse(
                    $lesson->date->toDateString().' '.$lesson->start_time->format('H:i')
                );

                return $now >= $start->subHours(48) && $now < $start;
            })
            ->values();
    }
}
