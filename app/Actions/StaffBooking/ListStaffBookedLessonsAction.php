<?php

declare(strict_types=1);

namespace App\Actions\StaffBooking;

use App\Actions\LessonsBooked\BuildBookedLessonRowsAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStaffBookedLessonsAction
{
    /**
     * @var array<string, list<OrderStatus>>
     */
    public const PAYMENT_STATES = BuildBookedLessonRowsAction::PAYMENT_STATES;

    public function __construct(
        protected BuildBookedLessonRowsAction $buildRows,
    ) {}

    /**
     * Lessons booked by the bookings team, newest invoice first.
     *
     * @param  string  $paymentState  'all' or a key of PAYMENT_STATES
     * @return array{bookings: LengthAwarePaginator, counts: array<string, int>}
     */
    public function __invoke(string $paymentState): array
    {
        $query = $this->baseQuery()
            ->with([...BuildBookedLessonRowsAction::RELATIONS, 'lessons' => BuildBookedLessonRowsAction::orderedLessons()])
            ->orderByDesc('invoice_sent_at')
            ->orderByDesc('id');

        BuildBookedLessonRowsAction::applyPaymentState($query, $paymentState);

        $orders = $query->paginate(25)->withQueryString();

        $orders->setCollection(($this->buildRows)($orders->getCollection()));

        return [
            'bookings' => $orders,
            'counts' => BuildBookedLessonRowsAction::countByPaymentState($this->baseQuery()),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function baseQuery(): Builder
    {
        return Order::query()->whereNotNull('booked_by_user_id');
    }
}
