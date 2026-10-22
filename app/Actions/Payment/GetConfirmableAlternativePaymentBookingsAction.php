<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Student;
use App\Support\AlternativePayments;
use Illuminate\Support\Collection;

class GetConfirmableAlternativePaymentBookingsAction
{
    /**
     * The pupil's weekly and pay-in-full bookings still awaiting their first
     * payment that the instructor can confirm as paid by an alternative method,
     * earliest lesson first. Empty when alternative payments are off for the
     * pupil. `amount_pence` is what the pupil pays the instructor now: the
     * first lesson's price (weekly) or every lesson's price (pay in full).
     *
     * @return Collection<int, array{order_id: int, payment_mode: string, package_name: string|null, lessons_count: int, first_lesson_date: string|null, first_lesson_start_time: string|null, first_lesson_end_time: string|null, amount_pence: int}>
     */
    public function __invoke(Student $student): Collection
    {
        if (! AlternativePayments::allowedForStudent($student)) {
            return collect();
        }

        return $student->orders()
            ->where('status', OrderStatus::PENDING)
            ->whereIn('payment_mode', AlternativePayments::paymentModeValues())
            ->with(['lessons' => fn ($query) => $query
                ->where('status', '!=', LessonStatus::CANCELLED)
                ->orderBy('date')
                ->orderBy('start_time')])
            ->get()
            ->each(fn (Order $order) => $order->setRelation('student', $student))
            ->filter(fn (Order $order) => AlternativePayments::canConfirmBooking($order))
            ->map(function (Order $order): array {
                $firstLesson = $order->lessons->first();

                return [
                    'order_id' => $order->id,
                    'payment_mode' => $order->payment_mode->value,
                    'package_name' => $order->package_name,
                    'lessons_count' => $order->lessons->count(),
                    'first_lesson_date' => $firstLesson?->date?->format('Y-m-d'),
                    'first_lesson_start_time' => $firstLesson?->start_time?->format('H:i'),
                    'first_lesson_end_time' => $firstLesson?->end_time?->format('H:i'),
                    'amount_pence' => $order->isUpfront()
                        ? (int) $order->lessons->sum('amount_pence')
                        : (int) ($firstLesson?->amount_pence ?? 0),
                ];
            })
            ->sortBy('first_lesson_date')
            ->values();
    }
}
