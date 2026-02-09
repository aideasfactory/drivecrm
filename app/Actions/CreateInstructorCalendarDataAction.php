<?php

namespace App\Actions;

use Carbon\Carbon;

class CreateInstructorCalendarDataAction
{
    /**
     * Generate calendar data for a new instructor.
     *
     * Creates calendars for:
     * - Next 3 days (today, tomorrow, day after)
     * - 4-5 random future dates
     * Each calendar gets 4-5 random 1-hour time slots
     *
     * @return array ['calendars' => [['date' => Carbon, 'items' => [...]], ...]]
     */
    public function __invoke(): array
    {
        $calendars = [];

        // 1. Generate next 3 days
        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::today()->addDays($i);
            $calendars[] = [
                'date' => $date,
                'items' => $this->generateTimeSlots(),
            ];
        }

        // 2. Generate 4-5 random future dates (between 3 days and 60 days from now)
        $randomDateCount = rand(4, 5);
        $randomDates = $this->generateRandomDates($randomDateCount, 3, 60);

        foreach ($randomDates as $date) {
            $calendars[] = [
                'date' => $date,
                'items' => $this->generateTimeSlots(),
            ];
        }

        return ['calendars' => $calendars];
    }

    /**
     * Generate 4-5 random time slots for a calendar.
     *
     * @return array [['start_time' => 'HH:MM', 'end_time' => 'HH:MM', 'is_available' => true], ...]
     */
    protected function generateTimeSlots(): array
    {
        $slotCount = rand(4, 5);
        $slots = [];
        $usedHours = [];

        // Possible start hours (8 AM to 5 PM for 1-hour slots ending by 6 PM)
        $availableHours = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17];

        // Shuffle to randomize
        shuffle($availableHours);

        // Take first $slotCount hours
        $selectedHours = array_slice($availableHours, 0, $slotCount);

        // Sort them so they appear in chronological order
        sort($selectedHours);

        foreach ($selectedHours as $hour) {
            $startTime = sprintf('%02d:00', $hour);
            $endTime = sprintf('%02d:00', $hour + 1);

            $slots[] = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => true,
            ];
        }

        return $slots;
    }

    /**
     * Generate random unique dates within a range.
     *
     * @param  int  $count  Number of dates to generate
     * @param  int  $minDays  Minimum days from today
     * @param  int  $maxDays  Maximum days from today
     * @return array Array of Carbon dates
     */
    protected function generateRandomDates(int $count, int $minDays, int $maxDays): array
    {
        $dates = [];
        $attempts = 0;
        $maxAttempts = 100; // Prevent infinite loop

        while (count($dates) < $count && $attempts < $maxAttempts) {
            $attempts++;

            // Random number of days between min and max
            $daysToAdd = rand($minDays, $maxDays);
            $randomDate = Carbon::today()->addDays($daysToAdd);

            // Check if this date is already in our array
            $dateString = $randomDate->format('Y-m-d');
            $exists = false;

            foreach ($dates as $existingDate) {
                if ($existingDate->format('Y-m-d') === $dateString) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $dates[] = $randomDate;
            }
        }

        return $dates;
    }
}
