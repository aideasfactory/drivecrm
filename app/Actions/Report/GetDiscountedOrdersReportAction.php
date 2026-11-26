<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetDiscountedOrdersReportAction
{
    /**
     * Paid bookings that used a discount code, newest first, optionally for
     * one code. Unpaid (pending / expired) bookings are left out: the
     * learner never paid, so the code was never used.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, codes: list<string>, totals: array{orders: int, discount_pence: int}, generated_at: string}
     */
    public function __invoke(?string $code = null): array
    {
        $orders = Order::query()
            ->whereNotNull('discount_code')
            ->whereIn('status', DiscountCode::USED_ORDER_STATUSES)
            ->when($code !== null && $code !== '', fn ($query) => $query->where('discount_code', $code))
            ->with(['student', 'instructor.user', 'discountCode'])
            ->latest()
            ->get();

        $rows = $orders->map(fn (Order $order): array => $this->row($order))->values();

        return [
            'rows' => $rows,
            'codes' => Order::query()
                ->whereNotNull('discount_code')
                ->whereIn('status', DiscountCode::USED_ORDER_STATUSES)
                ->distinct()
                ->orderBy('discount_code')
                ->pluck('discount_code')
                ->all(),
            'totals' => [
                'orders' => $rows->count(),
                'discount_pence' => (int) $rows->sum('discount_pence'),
            ],
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Order $order): array
    {
        $student = $order->student;
        $contact = $student?->getBookerDetails() ?? [];

        return [
            'order_id' => $order->id,
            'student_id' => $student?->id,
            'instructor_id' => $student?->instructor_id ?? $order->instructor_id,
            'learner_name' => $this->studentName($student) ?? 'Unknown learner',
            'learner_email' => $contact['email'] ?? $student?->email,
            'instructor_name' => $order->instructor?->user?->name,
            'package' => $order->package_name,
            'discount_code' => $order->discount_code,
            'discount_summary' => $order->discountCode?->formatted_percentage,
            'discount_pence' => (int) $order->discount_pence,
            'total_price_pence' => (int) $order->total_price_pence,
            'payment_mode' => $order->payment_mode?->value,
            'status' => $order->status?->value,
            'booked_at' => $order->created_at?->toIso8601String(),
        ];
    }

    protected function studentName(?Student $student): ?string
    {
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : null;
    }
}
