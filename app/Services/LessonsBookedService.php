<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\LessonsBooked\ListAllBookedLessonsAction;
use App\Actions\StaffBooking\ListStaffBookedLessonsAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LessonsBookedService extends BaseService
{
    public function __construct(
        protected ListStaffBookedLessonsAction $listStaffBookedLessons,
        protected ListAllBookedLessonsAction $listAllBookedLessons,
    ) {}

    /**
     * The "Lessons booked" listing for a tab. Not cached — unpaid bookings are
     * chased from this page, so it must always be current.
     *
     * @param  array{tab: string, payment: string, from: ?string, to: ?string}  $filters
     * @return array{bookings: LengthAwarePaginator, counts: array<string, int>}
     */
    public function list(array $filters): array
    {
        if ($filters['tab'] === 'all') {
            return ($this->listAllBookedLessons)($filters['payment'], $filters['from'], $filters['to']);
        }

        return ($this->listStaffBookedLessons)($filters['payment']);
    }
}
