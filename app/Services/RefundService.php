<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Refund\MarkRefundCompleteAction;
use App\Actions\Refund\ProcessStripeRefundAction;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RefundService extends BaseService
{
    public function __construct(
        protected ProcessStripeRefundAction $processStripeRefund,
        protected MarkRefundCompleteAction $markRefundComplete,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Refund>
     */
    public function paginate(?string $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return Refund::query()
            ->with([
                'lesson',
                'order',
                'student.user',
                'instructor.user',
                'requestedBy',
                'processedBy',
            ])
            ->when(
                $status && $status !== 'all',
                fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Running totals across every refund request (not just the current page).
     *
     * @return array{
     *     pending_count: int,
     *     pending_amount_pence: int,
     *     completed_count: int,
     *     completed_amount_pence: int,
     *     requested_count: int,
     *     requested_amount_pence: int
     * }
     */
    public function totals(): array
    {
        $pendingCount = Refund::query()->where('status', RefundStatus::PENDING)->count();
        $pendingAmount = (int) Refund::query()->where('status', RefundStatus::PENDING)->sum('amount_pence');
        $completedCount = Refund::query()->where('status', RefundStatus::COMPLETED)->count();
        $completedAmount = (int) Refund::query()->where('status', RefundStatus::COMPLETED)->sum('amount_pence');

        return [
            'pending_count' => $pendingCount,
            'pending_amount_pence' => $pendingAmount,
            'completed_count' => $completedCount,
            'completed_amount_pence' => $completedAmount,
            'requested_count' => $pendingCount + $completedCount,
            'requested_amount_pence' => $pendingAmount + $completedAmount,
        ];
    }

    public function processStripeRefund(Refund $refund, User $actor): Refund
    {
        return ($this->processStripeRefund)($refund, $actor);
    }

    public function markComplete(Refund $refund, User $actor): Refund
    {
        return ($this->markRefundComplete)($refund, $actor);
    }
}
