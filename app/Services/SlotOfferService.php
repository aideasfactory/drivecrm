<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Calendar\AcceptSlotOfferAction;
use App\Actions\Calendar\CancelSlotOfferAction;
use App\Actions\Calendar\CloseOpenSlotOffersForItemsAction;
use App\Actions\Calendar\CreateSlotOfferAction;
use App\Actions\Calendar\GetActiveSlotOffersForStudentAction;
use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\SlotOffer;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class SlotOfferService extends BaseService
{
    public function __construct(
        protected CreateSlotOfferAction $createSlotOffer,
        protected GetActiveSlotOffersForStudentAction $getActiveSlotOffersForStudent,
        protected AcceptSlotOfferAction $acceptSlotOffer,
        protected CancelSlotOfferAction $cancelSlotOffer,
        protected CloseOpenSlotOffersForItemsAction $closeOpenSlotOffersForItems,
    ) {}

    /**
     * @return array{offer: SlotOffer, notified_count: int}
     */
    public function createOffer(
        Instructor $instructor,
        CalendarItem $calendarItem,
        ?string $message,
        ?int $packageId,
        ?int $oneOffPricePence,
    ): array {
        return ($this->createSlotOffer)(
            $instructor,
            $calendarItem,
            $message,
            $packageId,
            $oneOffPricePence,
        );
    }

    public function cancelOffer(Instructor $instructor, CalendarItem $calendarItem): SlotOffer
    {
        return ($this->cancelSlotOffer)($instructor, $calendarItem);
    }

    /**
     * @return Collection<int, SlotOffer>
     */
    public function getActiveOffersForStudent(Student $student): Collection
    {
        return ($this->getActiveSlotOffersForStudent)($student);
    }

    /**
     * @return array{order: Order, checkout_url?: string|null}
     */
    public function acceptOffer(Student $student, SlotOffer $offer, PaymentMode $paymentMode, bool $returnCheckoutUrl = true): array
    {
        return ($this->acceptSlotOffer)($student, $offer, $paymentMode, $returnCheckoutUrl);
    }

    /**
     * @param  array<int, int>  $calendarItemIds
     */
    public function closeOpenOffersForItems(array $calendarItemIds, ?int $exceptOfferId = null): void
    {
        ($this->closeOpenSlotOffersForItems)($calendarItemIds, $exceptOfferId);
    }
}
