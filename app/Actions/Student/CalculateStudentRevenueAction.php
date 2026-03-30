<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Student;

class CalculateStudentRevenueAction
{
    /**
     * Calculate actual revenue for a student across all their orders.
     *
     * For upfront orders: uses the full package_total_price_pence (already paid in full).
     * For weekly orders: sums only lesson_payments where status is 'paid'.
     *
     * Expects orders and orders.lessons.lessonPayment to be eager loaded.
     */
    public function __invoke(Student $student): int
    {
        $revenuePence = 0;

        foreach ($student->orders as $order) {
            if ($order->payment_mode === PaymentMode::WEEKLY) {
                foreach ($order->lessons as $lesson) {
                    if ($lesson->lessonPayment && $lesson->lessonPayment->status === PaymentStatus::PAID) {
                        $revenuePence += $lesson->lessonPayment->amount_pence ?? 0;
                    }
                }
            } else {
                $revenuePence += $order->package_total_price_pence ?? 0;
            }
        }

        return $revenuePence;
    }
}
