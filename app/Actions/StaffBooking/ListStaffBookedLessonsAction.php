<?php

declare(strict_types=1);

namespace App\Actions\StaffBooking;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Enquiry;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Support\TestPassGuarantee;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListStaffBookedLessonsAction
{
    /**
     * Order statuses behind each payment state shown on the list.
     *
     * @var array<string, list<OrderStatus>>
     */
    public const PAYMENT_STATES = [
        'unpaid' => [OrderStatus::PENDING],
        'paid' => [OrderStatus::ACTIVE, OrderStatus::COMPLETED],
        'expired' => [OrderStatus::EXPIRED],
        'cancelled' => [OrderStatus::CANCELLED],
    ];

    /**
     * Lessons booked by the bookings team, newest invoice first.
     *
     * @param  string  $paymentState  'all' or a key of PAYMENT_STATES
     * @return array{bookings: LengthAwarePaginator, counts: array<string, int>}
     */
    public function __invoke(string $paymentState): array
    {
        $orders = $this->baseQuery()
            ->with([
                'student.pickupPoints',
                'instructor.user',
                'package',
                'manuallyRefundedBy:id,name',
                'lessons' => function ($query): void {
                    $query->orderBy('date')->orderBy('start_time');
                },
            ])
            ->when(isset(self::PAYMENT_STATES[$paymentState]), function (Builder $query) use ($paymentState): void {
                $query->whereIn('status', self::PAYMENT_STATES[$paymentState]);
            })
            ->orderByDesc('invoice_sent_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $enquiries = $this->enquiriesForOrders($orders->getCollection());

        return [
            'bookings' => $orders->through(
                fn (Order $order): array => $this->row($order, $enquiries->get($order->id)),
            ),
            'counts' => $this->counts(),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function baseQuery(): Builder
    {
        return Order::query()->whereNotNull('booked_by_user_id');
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $byStatus = $this->baseQuery()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['all' => (int) $byStatus->sum()];

        foreach (self::PAYMENT_STATES as $state => $statuses) {
            $counts[$state] = (int) collect($statuses)->sum(fn (OrderStatus $status): int => (int) ($byStatus[$status->value] ?? 0));
        }

        return $counts;
    }

    private function paymentState(Order $order): string
    {
        foreach (self::PAYMENT_STATES as $state => $statuses) {
            if (in_array($order->status, $statuses, true)) {
                return $state;
            }
        }

        return 'unpaid';
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Enquiry>
     */
    private function enquiriesForOrders(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($orderIds === []) {
            return collect();
        }

        return Enquiry::query()
            ->whereNotNull('data->staff_booking')
            ->where(function (Builder $query) use ($orderIds): void {
                foreach ($orderIds as $orderId) {
                    $query->orWhere('data->steps->step6->order_id', $orderId)
                        ->orWhere('data->steps->step6->order_id', (string) $orderId);
                }
            })
            ->get()
            ->keyBy(fn (Enquiry $enquiry): int => (int) ($enquiry->getStepData(6)['order_id'] ?? 0));
    }

    /**
     * @return array{
     *     id: int,
     *     invoice_sent_at: string|null,
     *     student_name: string,
     *     student_id: int|null,
     *     postcode: string|null,
     *     lesson_date: string|null,
     *     lesson_time: string|null,
     *     email: string|null,
     *     telephone: string|null,
     *     instructor_name: string|null,
     *     instructor_id: int|null,
     *     package: string,
     *     payment_state: string,
     *     can_mark_refunded: bool,
     *     manually_refunded_at: string|null,
     *     manually_refunded_by_name: string|null
     * }
     */
    private function row(Order $order, ?Enquiry $enquiry): array
    {
        $step1 = $enquiry?->getStepData(1) ?? [];
        $step4 = $enquiry?->getStepData(4) ?? [];
        $step5 = $enquiry?->getStepData(5) ?? [];
        $student = $order->student;
        $lesson = $this->firstOpenLesson($order);
        $startTime = $this->clock($lesson?->start_time) ?? $this->clockFromString($this->stringOrNull($step4['start_time'] ?? null));
        $endTime = $this->clock($lesson?->end_time) ?? $this->clockFromString($this->stringOrNull($step4['end_time'] ?? null));
        $contact = $this->contact($student, $step1);

        return [
            'id' => $order->id,
            'invoice_sent_at' => $order->invoice_sent_at?->toIso8601String(),
            'student_name' => $this->studentName($student, $step1, $step5),
            'student_id' => $student?->id,
            'postcode' => $this->postcode($student, $step1, $step5),
            'lesson_date' => $this->lessonDate($lesson, $step4),
            'lesson_time' => $startTime,
            'email' => $contact['email'],
            'telephone' => $contact['phone'],
            'instructor_name' => $order->instructor?->user?->name,
            'instructor_id' => $order->instructor_id,
            'package' => $this->packageLabel($order, $startTime, $endTime),
            'payment_state' => $this->paymentState($order),
            'can_mark_refunded' => ! $order->isImported(),
            'manually_refunded_at' => $order->manually_refunded_at?->toIso8601String(),
            'manually_refunded_by_name' => $order->manuallyRefundedBy?->name,
        ];
    }

    private function firstOpenLesson(Order $order): ?Lesson
    {
        return $order->lessons->first(
            fn (Lesson $lesson): bool => $lesson->status !== LessonStatus::CANCELLED,
        );
    }

    /**
     * @param  array<string, mixed>  $step1
     * @param  array<string, mixed>  $step5
     */
    private function studentName(?Student $student, array $step1, array $step5): string
    {
        $fromStudent = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        if ($fromStudent !== '') {
            return $fromStudent;
        }

        $learner = trim((string) ($step5['learner_first_name'] ?? '').' '.(string) ($step5['learner_last_name'] ?? ''));

        if ($learner !== '') {
            return $learner;
        }

        $booker = trim((string) ($step1['first_name'] ?? '').' '.(string) ($step1['last_name'] ?? ''));

        return $booker !== '' ? $booker : 'Unknown student';
    }

    /**
     * @param  array<string, mixed>  $step1
     * @return array{email: string|null, phone: string|null}
     */
    private function contact(?Student $student, array $step1): array
    {
        if ($student) {
            $details = $student->getBookerDetails();

            return [
                'email' => $this->stringOrNull($details['email'] ?? null) ?? $this->stringOrNull($step1['email'] ?? null),
                'phone' => $this->stringOrNull($details['phone'] ?? null) ?? $this->stringOrNull($step1['phone'] ?? null),
            ];
        }

        return [
            'email' => $this->stringOrNull($step1['email'] ?? null),
            'phone' => $this->stringOrNull($step1['phone'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $step1
     * @param  array<string, mixed>  $step5
     */
    private function postcode(?Student $student, array $step1, array $step5): ?string
    {
        $pickup = $this->stringOrNull($step5['pickup_postcode'] ?? null);

        if ($pickup) {
            return $pickup;
        }

        $booked = $this->stringOrNull($step1['postcode'] ?? null);

        if ($booked) {
            return $booked;
        }

        $point = $student?->pickupPoints->firstWhere('is_default', true)
            ?? $student?->pickupPoints->first();

        return $this->stringOrNull($point?->postcode);
    }

    /**
     * @param  array<string, mixed>  $step4
     */
    private function lessonDate(?Lesson $lesson, array $step4): ?string
    {
        if ($lesson?->date) {
            return $lesson->date->toDateString();
        }

        $date = $step4['date'] ?? null;

        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function packageLabel(Order $order, ?string $startTime, ?string $endTime): string
    {
        $lessonsCount = (int) ($order->package_lessons_count ?? $order->package?->lessons_count ?? $order->lessons->count());
        $hours = $this->formatHours(TestPassGuarantee::bookedHours($lessonsCount, $startTime, $endTime));
        $name = $this->stringOrNull($order->package_name ?? $order->package?->name);

        return $name ? $name.' ('.$hours.')' : $hours;
    }

    private function formatHours(float $hours): string
    {
        $normalized = round($hours, 2);
        $whole = abs($normalized - round($normalized)) < 0.001;
        $amount = $whole
            ? (string) (int) round($normalized)
            : rtrim(rtrim(number_format($normalized, 2, '.', ''), '0'), '.');

        return $amount.' '.($normalized === 1.0 ? 'hour' : 'hours');
    }

    private function clock(mixed $time): ?string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('H:i');
        }

        return $this->clockFromString(is_string($time) ? $time : null);
    }

    private function clockFromString(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
