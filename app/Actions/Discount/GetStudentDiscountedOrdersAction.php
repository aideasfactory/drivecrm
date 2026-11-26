<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Collection;

class GetStudentDiscountedOrdersAction
{
    /**
     * A pupil's bookings that had a discount code, newest first, whatever
     * their status, for the pupil profile.
     *
     * @return Collection<int, array{order_id: int, package: ?string, discount_code: ?string, discount_summary: ?string, discount_pence: int, total_price_pence: int, status: ?string, booked_at: ?string}>
     */
    public function __invoke(Student $student): Collection
    {
        return Order::query()
            ->where('student_id', $student->id)
            ->whereNotNull('discount_code')
            ->with('discountCode')
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                'order_id' => $order->id,
                'package' => $order->package_name,
                'discount_code' => $order->discount_code,
                'discount_summary' => $order->discountCode?->formatted_percentage,
                'discount_pence' => (int) $order->discount_pence,
                'total_price_pence' => (int) $order->total_price_pence,
                'status' => $order->status?->value,
                'booked_at' => $order->created_at?->toIso8601String(),
            ])
            ->values();
    }
}
