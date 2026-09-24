<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class GrantTestPassGuaranteeAction
{
    public function __construct(
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Flag the order's student as having Pass Your Test Guarantee. Call only
     * once the payment covering the guarantee has been confirmed. Safe to call
     * repeatedly (webhook + success redirect): the first grant wins.
     */
    public function __invoke(Order $order): ?Student
    {
        if (! $order->includes_test_pass_guarantee) {
            return null;
        }

        $student = $order->student;

        if (! $student || $student->hasTestPassGuarantee()) {
            return $student;
        }

        $student->forceFill([
            'test_pass_guarantee_at' => now(),
            'test_pass_guarantee_order_id' => $order->id,
        ])->save();

        $isFree = (int) $order->test_pass_guarantee_pence === 0;

        Log::info('Pass Your Test Guarantee granted', [
            'student_id' => $student->id,
            'order_id' => $order->id,
            'is_free' => $isFree,
            'charge_pence' => $order->test_pass_guarantee_pence,
        ]);

        try {
            ($this->logActivity)(
                $student,
                $isFree
                    ? "Pass Your Test Guarantee included free with order #{$order->id} ({$order->package_name}, paid in full)"
                    : "Pass Your Test Guarantee purchased with order #{$order->id} ({$order->formatted_test_pass_guarantee})",
                'payment',
                [
                    'type' => 'test_pass_guarantee_granted',
                    'order_id' => $order->id,
                    'charge_pence' => $order->test_pass_guarantee_pence,
                    'is_free' => $isFree,
                ],
                'Pass Your Test Guarantee added'
            );
        } catch (\Exception $e) {
            Log::error('Failed to log Pass Your Test Guarantee activity', [
                'student_id' => $student->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $student;
    }
}
