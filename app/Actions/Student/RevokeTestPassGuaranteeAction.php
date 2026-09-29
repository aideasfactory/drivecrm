<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class RevokeTestPassGuaranteeAction
{
    public function __construct(
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Remove the Pass Your Test Guarantee from the order's student when it was
     * granted by this order. Used when the booking is cancelled, because a
     * cancellation refunds the guarantee. A guarantee granted by a different
     * order is left alone. Returns true when the guarantee was removed.
     */
    public function __invoke(Order $order): bool
    {
        $student = $order->student;

        if (! $student || ! $student->hasTestPassGuarantee() || (int) $student->test_pass_guarantee_order_id !== (int) $order->id) {
            return false;
        }

        $student->forceFill([
            'test_pass_guarantee_at' => null,
            'test_pass_guarantee_order_id' => null,
        ])->save();

        Log::info('Pass Your Test Guarantee removed after cancellation', [
            'student_id' => $student->id,
            'order_id' => $order->id,
        ]);

        try {
            ($this->logActivity)(
                $student,
                "Pass Your Test Guarantee removed: booking #{$order->id} ({$order->package_name}) was cancelled and the guarantee refunded",
                'payment',
                [
                    'type' => 'test_pass_guarantee_revoked',
                    'order_id' => $order->id,
                ],
                'Pass Your Test Guarantee removed'
            );
        } catch (\Exception $e) {
            Log::error('Failed to log Pass Your Test Guarantee removal', [
                'student_id' => $student->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
