<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Support\BookingPayments;
use Illuminate\Support\Collection;

class GetOverdueLessonPaymentsToAutoCancelAction
{
    /**
     * Weekly lesson payments still unpaid more than
     * `auto_cancel_overdue_invoice_days` after they fell due, for lessons that
     * were neither signed off nor cancelled. Paid and refunded payments, those
     * confirmed as paid by an alternative method, lessons with a payout and
     * bookings still awaiting their first payment are never included.
     *
     * @return Collection<int, LessonPayment>
     */
    public function __invoke(): Collection
    {
        return LessonPayment::query()
            ->where('status', PaymentStatus::DUE)
            ->whereNull('auto_cancelled_at')
            ->whereNull('alternative_payment_confirmed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', BookingPayments::overdueInvoiceCutoffDate())
            ->whereHas('lesson', function ($query): void {
                $query->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
                    ->whereDoesntHave('payout');
            })
            ->whereHas('lesson.order', function ($query): void {
                $query->where('payment_mode', PaymentMode::WEEKLY)
                    ->where('status', '!=', OrderStatus::PENDING);
            })
            ->with(['lesson.order.student'])
            ->orderBy('due_date')
            ->get();
    }
}
