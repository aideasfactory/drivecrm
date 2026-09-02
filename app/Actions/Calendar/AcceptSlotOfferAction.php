<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\PaymentMode;
use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\SlotOffer;
use App\Models\Student;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptSlotOfferAction
{
    public function __construct(
        protected OrderService $orderService,
        protected CloseOpenSlotOffersForItemsAction $closeOpenSlotOffersForItems,
    ) {}

    /**
     * Book a short-notice offer for the given student.
     *
     * Availability is decided here under a row lock so two students cannot
     * take the same slot even if they tap at the same time.
     *
     * @return array{order: \App\Models\Order, checkout_url?: string|null}
     */
    public function __invoke(Student $student, SlotOffer $offer, PaymentMode $paymentMode, bool $returnCheckoutUrl = true): array
    {
        return DB::transaction(function () use ($student, $offer, $paymentMode, $returnCheckoutUrl): array {
            $item = CalendarItem::query()
                ->whereKey($offer->calendar_item_id)
                ->lockForUpdate()
                ->with('calendar')
                ->first();

            $lockedOffer = SlotOffer::query()
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOffer->loadMissing('package');

            if ($lockedOffer->status !== SlotOfferStatus::Open) {
                throw ValidationException::withMessages([
                    'slot_offer' => 'This short-notice lesson is no longer available.',
                ]);
            }

            if ($student->instructor_id !== $lockedOffer->instructor_id) {
                throw ValidationException::withMessages([
                    'slot_offer' => 'This offer is not available to you.',
                ]);
            }

            if (! $item || ! $item->isEmptyAvailability()) {
                throw ValidationException::withMessages([
                    'slot_offer' => 'This short-notice lesson has already been taken.',
                ]);
            }

            $package = $lockedOffer->package;

            if (! $package || ! $package->active) {
                throw ValidationException::withMessages([
                    'slot_offer' => 'The package for this offer is no longer available.',
                ]);
            }

            $result = $this->orderService->bookLessonsFromCalendarItem(
                $student,
                $package,
                $paymentMode,
                $item,
                $returnCheckoutUrl,
            );

            $lockedOffer->update([
                'status' => SlotOfferStatus::Booked,
                'student_id' => $student->id,
                'booked_at' => now(),
            ]);

            ($this->closeOpenSlotOffersForItems)([$item->id], $lockedOffer->id);

            return $result;
        });
    }
}
