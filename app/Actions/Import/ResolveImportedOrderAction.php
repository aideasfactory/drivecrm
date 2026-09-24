<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;

class ResolveImportedOrderAction
{
    public const PACKAGE_NAME = 'Imported lessons';

    /**
     * Get (or create) the single imported order that holds a student's
     * imported lessons with this instructor.
     *
     * Lessons must hang off an order, and orders off a package, so each
     * instructor gets one hidden (inactive) £0 "Imported lessons" package.
     * The order's `payment_mode = imported` is what tells sign-off to skip
     * Stripe entirely.
     */
    public function __invoke(Instructor $instructor, Student $student): Order
    {
        $package = Package::query()->firstOrCreate(
            [
                'instructor_id' => $instructor->id,
                'name' => self::PACKAGE_NAME,
                'active' => false,
            ],
            [
                'total_price_pence' => 0,
                'lessons_count' => 1,
            ],
        );

        return Order::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'instructor_id' => $instructor->id,
                'payment_mode' => PaymentMode::IMPORTED,
            ],
            [
                'package_id' => $package->id,
                'package_name' => self::PACKAGE_NAME,
                'package_total_price_pence' => 0,
                'package_lesson_price_pence' => 0,
                'package_lessons_count' => 0,
                'booking_fee_pence' => 0,
                'digital_fee_pence' => 0,
                'total_price_pence' => 0,
                'status' => OrderStatus::ACTIVE,
            ],
        );
    }
}
