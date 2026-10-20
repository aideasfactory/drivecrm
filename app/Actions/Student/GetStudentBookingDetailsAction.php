<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Enquiry;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class GetStudentBookingDetailsAction
{
    /**
     * How many of the pupil's most recent orders to search for a booking enquiry.
     */
    private const ORDERS_TO_SEARCH = 10;

    /**
     * Address and date of birth captured when the pupil booked, for pupils
     * whose profile has no stored value yet. Uses the same postcode priority
     * as the "Lessons booked" page: pickup postcode, then the postcode searched
     * at step 1, then the pupil's default pickup point.
     *
     * @return array{address_line_1: string|null, address_line_2: string|null, city: string|null, postcode: string|null, date_of_birth: string|null}
     */
    public function __invoke(Student $student): array
    {
        $enquiry = $this->latestBookingEnquiry($student);
        $step1 = $enquiry?->getStepData(1) ?? [];
        $step5 = $enquiry?->getStepData(5) ?? [];

        $pickupPoint = $student->pickupPoints()->orderByDesc('is_default')->oldest()->first();

        return [
            'address_line_1' => $this->stringOrNull($step5['pickup_address_line_1'] ?? null) ?? $this->stringOrNull($pickupPoint?->address),
            'address_line_2' => $this->stringOrNull($step5['pickup_address_line_2'] ?? null),
            'city' => $this->stringOrNull($step5['pickup_city'] ?? null),
            'postcode' => $this->stringOrNull($step5['pickup_postcode'] ?? null)
                ?? $this->stringOrNull($step1['postcode'] ?? null)
                ?? $this->stringOrNull($pickupPoint?->postcode),
            'date_of_birth' => $this->dateOrNull($step5['learner_dob'] ?? null),
        ];
    }

    private function latestBookingEnquiry(Student $student): ?Enquiry
    {
        $orderIds = $student->orders()
            ->latest()
            ->limit(self::ORDERS_TO_SEARCH)
            ->pluck('id')
            ->all();

        if ($orderIds === []) {
            return null;
        }

        return Enquiry::query()
            ->where(function (Builder $query) use ($orderIds): void {
                foreach ($orderIds as $orderId) {
                    $query->orWhere('data->steps->step6->order_id', $orderId)
                        ->orWhere('data->steps->step6->order_id', (string) $orderId);
                }
            })
            ->latest()
            ->first();
    }

    private function dateOrNull(mixed $value): ?string
    {
        $date = $this->stringOrNull($value);

        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
