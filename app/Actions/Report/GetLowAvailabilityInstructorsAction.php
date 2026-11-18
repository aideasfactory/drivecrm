<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Actions\Instructor\GetInstructorListAction;
use App\Actions\Instructor\GetLowAvailabilitySettingsAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetLowAvailabilityInstructorsAction
{
    public function __construct(
        protected GetInstructorListAction $getInstructorList,
        protected GetLowAvailabilitySettingsAction $getLowAvailabilitySettings,
    ) {}

    /**
     * Instructors flagged as low availability on the Instructors list, fewest
     * slots added first. `week_starts` holds the first day of each rolling
     * 7-day week the slot counts cover.
     *
     * @return array{rows: Collection<int, array{instructor_id: int, name: string, email: string, phone: ?string, available_slots_by_week: list<int>, total_available_slots: int}>, settings: array{weeks: int, min_slots_per_week: int}, week_starts: list<string>, generated_at: string}
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();
        $settings = ($this->getLowAvailabilitySettings)();

        $rows = ($this->getInstructorList)(['low_availability' => GetInstructorListAction::LOW_AVAILABILITY])
            ->sortBy([['total_available_slots', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn (array $row): array => [
                'instructor_id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'available_slots_by_week' => $row['available_slots_by_week'],
                'total_available_slots' => $row['total_available_slots'],
            ]);

        return [
            'rows' => $rows,
            'settings' => $settings,
            'week_starts' => array_map(
                fn (int $week): string => $now->startOfDay()->addWeeks($week)->toDateString(),
                range(0, $settings['weeks'] - 1),
            ),
            'generated_at' => $now->toIso8601String(),
        ];
    }
}
