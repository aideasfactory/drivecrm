<?php

declare(strict_types=1);

namespace App\Actions\LessonsBooked;

use App\Enums\PaymentMode;
use App\Models\Order;
use App\Support\BookingPayments;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListAllBookedLessonsAction
{
    public function __construct(
        protected BuildBookedLessonRowsAction $buildRows,
    ) {}

    /**
     * Every booking, whoever made it (website, bookings team, CRM, learner app
     * or instructor app), newest first. Imported legacy orders are left out.
     *
     * @param  string  $paymentState  'all' or a key of BuildBookedLessonRowsAction::PAYMENT_STATES
     * @param  ?string  $bookedFrom  Y-m-d (UK) — only orders placed on/after this date
     * @param  ?string  $bookedTo  Y-m-d (UK) — only orders placed on/before this date
     * @return array{bookings: LengthAwarePaginator, counts: array<string, int>}
     */
    public function __invoke(string $paymentState, ?string $bookedFrom = null, ?string $bookedTo = null): array
    {
        $query = $this->baseQuery($bookedFrom, $bookedTo)
            ->with([...BuildBookedLessonRowsAction::RELATIONS, 'lessons' => BuildBookedLessonRowsAction::orderedLessons()])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        BuildBookedLessonRowsAction::applyPaymentState($query, $paymentState);

        $orders = $query->paginate(25)->withQueryString();

        $orders->setCollection(($this->buildRows)($orders->getCollection()));

        return [
            'bookings' => $orders,
            'counts' => BuildBookedLessonRowsAction::countByPaymentState($this->baseQuery($bookedFrom, $bookedTo)),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function baseQuery(?string $bookedFrom, ?string $bookedTo): Builder
    {
        return Order::query()
            ->where('payment_mode', '!=', PaymentMode::IMPORTED->value)
            ->when($bookedFrom, function (Builder $query) use ($bookedFrom): void {
                $query->where('created_at', '>=', CarbonImmutable::parse($bookedFrom, BookingPayments::timezone())->startOfDay()->utc());
            })
            ->when($bookedTo, function (Builder $query) use ($bookedTo): void {
                $query->where('created_at', '<=', CarbonImmutable::parse($bookedTo, BookingPayments::timezone())->endOfDay()->utc());
            });
    }
}
