<?php

namespace App\Services;

use App\Actions\CreateInstructorCalendarDataAction;
use App\Actions\FetchPostcodeCoordinatesAction;
use App\Actions\FindInstructorsByPostcodeSectorAction;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstructorService
{
    public function __construct(
        protected FetchPostcodeCoordinatesAction $fetchPostcodeCoordinates,
        protected FindInstructorsByPostcodeSectorAction $findInstructorsByPostcodeSector,
        protected CreateInstructorCalendarDataAction $createInstructorCalendarData
    ) {}

    /**
     * Create a new instructor with user account and locations.
     *
     * @return array ['success' => bool, 'instructor' => Instructor|null, 'error' => string|null]
     */
    public function createInstructor(array $data): array
    {
        try {
            DB::beginTransaction();

            // 1. Fetch coordinates from postcode
            $coordinates = ($this->fetchPostcodeCoordinates)($data['postcode']);

            if (! $coordinates || ! $coordinates['latitude'] || ! $coordinates['longitude']) {
                return [
                    'success' => false,
                    'instructor' => null,
                    'error' => 'Unable to fetch coordinates for the provided postcode. Please check the postcode and try again.',
                ];
            }

            // 2. Create user account
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'), // Default password
                'role' => UserRole::INSTRUCTOR,
            ]);

            // 3. Create instructor profile
            $instructor = Instructor::create([
                'user_id' => $user->id,
                'transmission_type' => $data['transmission_type'],
                'bio' => $data['bio'] ?? null,
                'address' => $data['address'] ?? null,
                'postcode' => $data['postcode'],
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'status' => 'active',
                'priority' => false,
                'onboarding_complete' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
            ]);

            // 4. Create instructor locations (postcode sectors)
            if (! empty($data['locations']) && is_array($data['locations'])) {
                foreach ($data['locations'] as $postcodeSector) {
                    // Skip empty entries
                    if (empty(trim($postcodeSector))) {
                        continue;
                    }

                    Location::create([
                        'instructor_id' => $instructor->id,
                        'postcode_sector' => strtoupper(trim($postcodeSector)),
                    ]);
                }
            }

            // 5. Create sample calendar data for testing
            $calendarData = ($this->createInstructorCalendarData)();

            foreach ($calendarData['calendars'] as $calendarInfo) {
                // Create calendar for the date
                $calendar = Calendar::create([
                    'instructor_id' => $instructor->id,
                    'date' => $calendarInfo['date'],
                ]);

                // Create time slots for this calendar
                foreach ($calendarInfo['items'] as $itemData) {
                    CalendarItem::create([
                        'calendar_id' => $calendar->id,
                        'start_time' => $itemData['start_time'],
                        'end_time' => $itemData['end_time'],
                        'is_available' => $itemData['is_available'],
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'instructor' => $instructor->load('user', 'locations'),
                'error' => null,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'instructor' => null,
                'error' => 'Failed to create instructor: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Find instructors by postcode sector.
     *
     * @param  string  $postcode  Full postcode (e.g., "TS7 1AB")
     * @return Collection Formatted instructor data
     */
    public function findByPostcode(string $postcode): Collection
    {
        return ($this->findInstructorsByPostcodeSector)($postcode);
    }

    public function nextAvailableDate(Instructor $instructor): ?string
    {
        $nextSlot = CalendarItem::query()
            ->join('calendars', 'calendar_items.calendar_id', '=', 'calendars.id')
            ->where('calendars.instructor_id', $instructor->id)
            ->where('calendar_items.is_available', true)
            ->whereRaw("STR_TO_DATE(CONCAT(calendars.date, ' ', calendar_items.start_time), '%Y-%m-%d %H:%i:%s') >= NOW()")
            ->orderBy('calendars.date')
            ->orderBy('calendar_items.start_time')
            ->selectRaw('calendars.date as calendar_date, calendar_items.start_time as item_start_time')
            ->first();

        if (! $nextSlot) {
            return null;
        }

        $date = \Carbon\Carbon::parse($nextSlot->calendar_date);
        $time = \Carbon\Carbon::parse($nextSlot->item_start_time);

        return $date->format('Y-m-d').' - '.$time->format('H:i:s');
    }
}
