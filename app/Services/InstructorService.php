<?php

namespace App\Services;

use App\Actions\CreateInstructorCalendarDataAction;
use App\Actions\FetchPostcodeCoordinatesAction;
use App\Actions\FindInstructorsByPostcodeSectorAction;
use App\Actions\Instructor\CreateCalendarItemAction;
use App\Actions\Instructor\CreateInstructorLocationAction;
use App\Actions\Instructor\CreateInstructorPackageAction;
use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Actions\Instructor\DeleteInstructorLocationAction;
use App\Actions\Instructor\GetInstructorCalendarAction;
use App\Actions\Instructor\GetInstructorLocationsAction;
use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Actions\Instructor\GetInstructorPupilsAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Shared\Message\SendBroadcastMessageAction;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstructorService
{
    public function __construct(
        protected FetchPostcodeCoordinatesAction $fetchPostcodeCoordinates,
        protected FindInstructorsByPostcodeSectorAction $findInstructorsByPostcodeSector,
        protected CreateInstructorCalendarDataAction $createInstructorCalendarData,
        protected GetInstructorPackagesAction $getInstructorPackages,
        protected CreateInstructorPackageAction $createInstructorPackage,
        protected GetInstructorLocationsAction $getInstructorLocations,
        protected CreateInstructorLocationAction $createInstructorLocation,
        protected DeleteInstructorLocationAction $deleteInstructorLocation,
        protected GetInstructorCalendarAction $getInstructorCalendar,
        protected CreateCalendarItemAction $createCalendarItem,
        protected DeleteCalendarItemAction $deleteCalendarItem,
        protected CreatePupilAction $createPupil,
        protected GetInstructorPupilsAction $getInstructorPupils,
        protected SendBroadcastMessageAction $sendBroadcastMessage,
        protected LogActivityAction $logActivity
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

            // random number between 1 and 5
            $avatarNumber = rand(1, 5);

            // 3. Create instructor profile
            $instructor = Instructor::create([
                'user_id' => $user->id,
                'bio' => $data['bio'] ?? null,
                'address' => $data['address'] ?? null,
                'postcode' => $data['postcode'],
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'status' => 'active',
                'priority' => false,
                'rating' => 1,
                'onboarding_complete' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'meta' => [
                    'transmission_type' => $data['transmission_type'] ?? null,
                    'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-'.$avatarNumber.'.jpg',
                ],
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

    /**
     * Get all packages available to an instructor.
     *
     * @param  bool  $onlyActive  Filter to only active packages
     * @return Collection Formatted package data
     */
    public function getPackages(Instructor $instructor, bool $onlyActive = true): Collection
    {
        return ($this->getInstructorPackages)($instructor, $onlyActive);
    }

    /**
     * Create a new bespoke package for an instructor.
     */
    public function createPackage(Instructor $instructor, array $data): \App\Models\Package
    {
        return ($this->createInstructorPackage)($instructor, $data);
    }

    /**
     * Get all coverage locations for an instructor.
     *
     * @return Collection Formatted location data
     */
    public function getLocations(Instructor $instructor): Collection
    {
        return ($this->getInstructorLocations)($instructor);
    }

    /**
     * Add a new coverage location for an instructor.
     *
     * @param  string  $postcodeSector  Postcode sector (e.g., "TS7", "WR14")
     * @return Location The created location
     */
    public function addLocation(Instructor $instructor, string $postcodeSector): Location
    {
        return ($this->createInstructorLocation)($instructor, $postcodeSector);
    }

    /**
     * Remove a coverage location.
     *
     * @return bool Whether the deletion was successful
     */
    public function removeLocation(Location $location): bool
    {
        return ($this->deleteInstructorLocation)($location);
    }

    /**
     * Get instructor's calendar with all calendar items for specified date range.
     *
     * @param  Carbon|null  $startDate  Start date (defaults to current week start)
     * @param  Carbon|null  $endDate  End date (defaults to current week end)
     * @return Collection Collection of calendar dates with their items
     */
    public function getCalendar(
        Instructor $instructor,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        return ($this->getInstructorCalendar)($instructor, $startDate, $endDate);
    }

    /**
     * Add a new calendar item (time slot) for an instructor.
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     * @param  string  $endTime  End time in H:i format
     * @return CalendarItem The created calendar item
     */
    public function addCalendarItem(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime
    ): CalendarItem {
        return ($this->createCalendarItem)($instructor, $date, $startTime, $endTime);
    }

    /**
     * Remove a calendar item (time slot).
     *
     * @return bool Whether the deletion was successful
     */
    public function removeCalendarItem(CalendarItem $calendarItem): bool
    {
        return ($this->deleteCalendarItem)($calendarItem);
    }

    /**
     * Get all students (pupils) belonging to an instructor.
     *
     * @param  string|null  $search  Optional search term
     * @return Collection Formatted pupil data
     */
    public function getPupils(Instructor $instructor, ?string $search = null): Collection
    {
        return ($this->getInstructorPupils)($instructor, $search);
    }

    /**
     * Send a broadcast message to all of an instructor's students.
     *
     * @return Collection Created messages
     */
    public function broadcastMessage(Instructor $instructor, string $message): Collection
    {
        $students = \App\Models\Student::where('instructor_id', $instructor->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->toArray();

        $messages = ($this->sendBroadcastMessage)($instructor->user, $students, $message);

        ($this->logActivity)(
            $instructor,
            'Broadcast message sent to '.count($students).' pupils',
            'message',
            ['message' => $message, 'recipient_count' => count($students)]
        );

        return $messages;
    }

    /**
     * Create a new pupil assigned to an instructor.
     *
     * @return \App\Models\Student The created student
     */
    public function addPupil(Instructor $instructor, array $data): \App\Models\Student
    {
        $student = ($this->createPupil)($instructor, $data);

        ($this->logActivity)(
            $instructor,
            'Pupil '.$data['first_name'].' '.$data['surname'].' added',
            'student',
            ['student_id' => $student->id, 'email' => $data['email']]
        );

        return $student;
    }
}
