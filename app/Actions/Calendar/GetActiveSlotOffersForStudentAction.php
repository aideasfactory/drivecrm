<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\SlotOfferStatus;
use App\Models\SlotOffer;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class GetActiveSlotOffersForStudentAction
{
    /**
     * Active short-notice offers for the student's instructor that are still bookable.
     *
     * @return Collection<int, SlotOffer>
     */
    public function __invoke(Student $student): Collection
    {
        if (! $student->instructor_id) {
            return new Collection;
        }

        $today = Carbon::today()->toDateString();

        return SlotOffer::query()
            ->where('instructor_id', $student->instructor_id)
            ->where('status', SlotOfferStatus::Open)
            ->whereHas('calendarItem', function ($query) use ($today): void {
                $query->where('is_available', true)
                    ->whereNull('status')
                    ->whereDoesntHave('lessons')
                    ->whereHas('calendar', fn ($calendarQuery) => $calendarQuery->whereDate('date', '>=', $today));
            })
            ->with(['package', 'calendarItem.calendar', 'instructor.user'])
            ->orderBy('id')
            ->get();
    }
}
