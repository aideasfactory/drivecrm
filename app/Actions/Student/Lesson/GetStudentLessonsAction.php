<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonCardStatus;
use App\Enums\LessonStatus;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetStudentLessonsAction
{
    /**
     * Fetch all lessons for a student across all orders.
     *
     * Returns lessons with related order, instructor, calendar item,
     * lesson payment, payout, reflective log, and resource data.
     * Each lesson includes a computed card_status.
     */
    public function __invoke(Student $student): Collection
    {
        $lessons = $student->orders()
            ->with([
                'lessons' => fn ($query) => $query
                    ->where('status', '!=', LessonStatus::DRAFT)
                    ->with([
                        'instructor.user:id,name',
                        'calendarItem.calendar:id,date',
                        'lessonPayment:id,lesson_id,amount_pence,status,paid_at,stripe_invoice_id',
                        'payout:id,lesson_id,status,amount_pence,stripe_transfer_id,paid_at',
                        'reflectiveLog:id,lesson_id',
                        'resources:id,title,resource_type,video_url,file_path,file_name,file_size,mime_type,thumbnail_url',
                    ])
                    ->orderBy('date')
                    ->orderBy('start_time'),
                'package:id,name',
            ])
            ->get()
            ->flatMap(fn ($order) => $order->lessons->map(function ($lesson) use ($order) {
                return [
                    'id' => $lesson->id,
                    'order_id' => $order->id,
                    'instructor_id' => $lesson->instructor_id,
                    'instructor_name' => $lesson->instructor?->user?->name,
                    'package_name' => $order->package_name ?? $order->package?->name,
                    'amount_pence' => $lesson->amount_pence,
                    'date' => $lesson->date?->format('Y-m-d'),
                    'start_time' => $lesson->start_time?->format('H:i'),
                    'end_time' => $lesson->end_time?->format('H:i'),
                    'status' => $lesson->status->value,
                    'completed_at' => $lesson->completed_at?->toISOString(),
                    'summary' => $lesson->summary,
                    'lesson_payment_id' => $lesson->lessonPayment?->id,
                    'payment_status' => $lesson->lessonPayment?->status?->value ?? ($order->isUpfront() && $order->isActive() ? 'paid' : null),
                    'has_stripe_invoice' => $lesson->lessonPayment?->stripe_invoice_id !== null,
                    'payment_mode' => $order->payment_mode->value,
                    'payout_status' => $lesson->payout?->status?->value,
                    'has_payout' => $lesson->payout !== null,
                    'calendar_date' => $lesson->calendarItem?->calendar?->date?->format('Y-m-d'),
                    'has_reflective_log' => $lesson->reflectiveLog !== null,
                    'resources_count' => $lesson->resources->count(),
                    '_date_obj' => $lesson->date,
                    '_completed_at' => $lesson->completed_at,
                ];
            }))
            ->sortBy([
                ['date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();

        $today = Carbon::today();
        $nextLessonFound = false;

        return $lessons->map(function (array $lesson) use ($today, &$nextLessonFound) {
            $lessonDate = $lesson['_date_obj'];
            $isCompleted = $lesson['_completed_at'] !== null;
            $isPast = $lessonDate && $lessonDate->lt($today);
            $isToday = $lessonDate && $lessonDate->isToday();

            if ($isCompleted) {
                $cardStatus = LessonCardStatus::SignedOff->value;
            } elseif ($isPast) {
                $cardStatus = LessonCardStatus::NeedsSignOff->value;
            } elseif (! $nextLessonFound && ($isToday || ($lessonDate && $lessonDate->gt($today)))) {
                $nextLessonFound = true;
                $cardStatus = LessonCardStatus::Current->value;
            } else {
                $cardStatus = LessonCardStatus::Upcoming->value;
            }

            unset($lesson['_date_obj'], $lesson['_completed_at']);
            $lesson['card_status'] = $cardStatus;

            return $lesson;
        })
            ->sortBy([
                ['date', 'desc'],
                ['start_time', 'desc'],
            ])
            ->values();
    }
}
