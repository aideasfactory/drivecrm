<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Package\FindOrCreateOneOffPackageAction;
use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Package;
use App\Models\SlotOffer;
use App\Models\Student;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSlotOfferAction
{
    public function __construct(
        protected FindOrCreateOneOffPackageAction $findOrCreateOneOffPackage,
        protected PushNotificationService $pushNotificationService,
    ) {}

    /**
     * Create or refresh a short-notice offer on an open diary slot.
     *
     * @return array{offer: SlotOffer, notified_count: int}
     */
    public function __invoke(
        Instructor $instructor,
        CalendarItem $calendarItem,
        ?string $message,
        ?int $packageId,
        ?int $oneOffPricePence,
    ): array {
        $calendarItem->loadMissing('calendar');

        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'Calendar item not found for this instructor.',
            ]);
        }

        if (! $calendarItem->isEmptyAvailability()) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'Only empty available diary slots can be offered.',
            ]);
        }

        $package = $this->resolvePackage($instructor, $packageId, $oneOffPricePence);

        $offer = DB::transaction(function () use ($instructor, $calendarItem, $message, $package): SlotOffer {
            $item = CalendarItem::query()
                ->whereKey($calendarItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $item->isEmptyAvailability()) {
                throw ValidationException::withMessages([
                    'calendar_item_id' => 'This diary slot is no longer available.',
                ]);
            }

            $offer = SlotOffer::query()->firstOrNew([
                'calendar_item_id' => $item->id,
            ]);

            if ($offer->exists && $offer->status === SlotOfferStatus::Booked) {
                throw ValidationException::withMessages([
                    'calendar_item_id' => 'This diary slot has already been booked.',
                ]);
            }

            $offer->fill([
                'instructor_id' => $instructor->id,
                'package_id' => $package->id,
                'student_id' => null,
                'message' => $message,
                'status' => SlotOfferStatus::Open,
                'booked_at' => null,
            ]);
            $offer->save();

            return $offer->load(['package', 'calendarItem.calendar']);
        });

        $notifiedCount = $this->notifyStudents($instructor, $offer);

        return [
            'offer' => $offer,
            'notified_count' => $notifiedCount,
        ];
    }

    private function resolvePackage(Instructor $instructor, ?int $packageId, ?int $oneOffPricePence): Package
    {
        if ($oneOffPricePence !== null) {
            return ($this->findOrCreateOneOffPackage)($instructor, $oneOffPricePence);
        }

        $package = Package::query()
            ->where('id', $packageId)
            ->where('active', true)
            ->where('instructor_id', $instructor->id)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => 'Please select one of this instructor\'s active packages.',
            ]);
        }

        if ($package->lessons_count === 1) {
            return $package;
        }

        return ($this->findOrCreateOneOffPackage)($instructor, $package->lesson_price_pence);
    }

    private function notifyStudents(Instructor $instructor, SlotOffer $offer): int
    {
        $date = $offer->calendarItem?->calendar?->date?->format('j M Y');
        $start = $offer->calendarItem?->start_time
            ? substr((string) $offer->calendarItem->start_time, 0, 5)
            : null;

        $defaultBody = $date && $start
            ? "A short-notice lesson is available on {$date} at {$start}."
            : 'A short-notice lesson is available with your instructor.';

        $body = $offer->message !== null && trim($offer->message) !== ''
            ? trim($offer->message)
            : $defaultBody;

        $data = [
            'type' => 'slot_offer',
            'slot_offer_id' => $offer->id,
            'calendar_item_id' => $offer->calendar_item_id,
        ];

        $count = 0;

        Student::query()
            ->where('instructor_id', $instructor->id)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->each(function (Student $student) use ($body, $data, &$count): void {
                $notification = $this->pushNotificationService->queueIfHasToken(
                    $student->user,
                    'Short Notice Lesson Available',
                    $body,
                    $data,
                );

                if ($notification) {
                    $count++;
                }
            });

        return $count;
    }
}
