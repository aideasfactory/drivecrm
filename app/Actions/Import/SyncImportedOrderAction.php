<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Order;

class SyncImportedOrderAction
{
    /**
     * Bring an imported order in line with the lessons now attached to it:
     * lesson count snapshot, and completed once nothing is left to sign off.
     */
    public function __invoke(Order $order): void
    {
        $lessonCount = $order->lessons()->where('status', '!=', LessonStatus::CANCELLED)->count();

        $hasOpenLessons = $order->lessons()
            ->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
            ->exists();

        $order->update([
            'package_lessons_count' => $lessonCount,
            'status' => $hasOpenLessons ? OrderStatus::ACTIVE : OrderStatus::COMPLETED,
        ]);
    }
}
