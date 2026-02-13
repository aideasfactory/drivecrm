<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Order;

class CheckOrderCompletionAction
{
    /**
     * Check if all lessons in an order are completed.
     * If so, mark the order as completed.
     */
    public function __invoke(Order $order): bool
    {
        $allCompleted = $order->lessons()
            ->where('status', '!=', LessonStatus::COMPLETED)
            ->doesntExist();

        if ($allCompleted) {
            $order->status = OrderStatus::COMPLETED;
            $order->save();
        }

        return $allCompleted;
    }
}
