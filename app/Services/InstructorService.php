<?php

namespace App\Services;

use App\Actions\Calendar\DetectCalendarClashesAction;
use App\Actions\FetchPostcodeCoordinatesAction;
use App\Actions\FindInstructorsByPostcodeSectorAction;
use App\Actions\Instructor\BulkImportInstructorsAction;
use App\Actions\Instructor\CreateCalendarItemAction;
use App\Actions\Instructor\CreateInstructorLocationAction;
use App\Actions\Instructor\CreateInstructorPackageAction;
use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Instructor\CreateRecurringCalendarItemsAction;
use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Actions\Instructor\DeleteInstructorLocationAction;
use App\Actions\Instructor\DeleteInstructorProfilePictureAction;
use App\Actions\Instructor\DeleteRecurringCalendarItemsAction;
use App\Actions\Instructor\GetGroupedStudentsAction;
use App\Actions\Instructor\GetInstructorCalendarAction;
use App\Actions\Instructor\GetInstructorDayLessonsAction;
use App\Actions\Instructor\GetInstructorLocationsAction;
use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Actions\Instructor\GetInstructorPayoutsAction;
use App\Actions\Instructor\GetInstructorPupilsAction;
use App\Actions\Instructor\UpdateCalendarItemAction;
use App\Actions\Instructor\UpdateInstructorProfileAction;
use App\Actions\Instructor\UploadInstructorProfilePictureAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Shared\Message\SendBroadcastMessageAction;
use App\Enums\RecurrencePattern;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CalendarClashDetectedNotification;
use App\Notifications\LessonRescheduledNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstructorService extends BaseService
{
    public function __construct(
        protected FetchPostcodeCoordinatesAction $fetchPostcodeCoordinates,
        protected FindInstructorsByPostcodeSectorAction $findInstructorsByPostcodeSector,
        protected BulkImportInstructorsAction $bulkImportInstructors,
        protected GetInstructorPackagesAction $getInstructorPackages,
        protected CreateInstructorPackageAction $createInstructorPackage,
        protected GetInstructorLocationsAction $getInstructorLocations,
        protected CreateInstructorLocationAction $createInstructorLocation,
        protected DeleteInstructorLocationAction $deleteInstructorLocation,
        protected GetInstructorCalendarAction $getInstructorCalendar,
        protected CreateCalendarItemAction $createCalendarItem,
        protected DeleteCalendarItemAction $deleteCalendarItem,
        protected UpdateCalendarItemAction $updateCalendarItem,
        protected CreateRecurringCalendarItemsAction $createRecurringCalendarItems,
        protected DeleteRecurringCalendarItemsAction $deleteRecurringCalendarItems,
        protected CreatePupilAction $createPupil,
        protected GetInstructorPayoutsAction $getInstructorPayouts,
        protected GetInstructorDayLessonsAction $getInstructorDayLessons,
        protected GetGroupedStudentsAction $getGroupedStudents,
        protected GetInstructorPupilsAction $getInstructorPupils,
        protected SendBroadcastMessageAction $sendBroadcastMessage,
        protected LogActivityAction $logActivity,
        protected UpdateInstructorProfileAction $updateInstructorProfile,
        protected UploadInstructorProfilePictureAction $uploadProfilePicture,
        protected DeleteInstructorProfilePictureAction $deleteProfilePicture,
        protected DetectCalendarClashesAction $detectCalendarClashes
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
                'rating' => 4,
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
     * @param  bool  $isAvailable  Whether the slot is available
     * @param  string|null  $notes  Optional notes about the slot
     * @param  string|null  $unavailabilityReason  Reason for unavailability
     * @return CalendarItem The created calendar item
     */
    public function addCalendarItem(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime,
        bool $isAvailable = true,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null,
        bool $isPracticalTest = false
    ): CalendarItem {
        $item = ($this->createCalendarItem)($instructor, $date, $startTime, $endTime, $isAvailable, $notes, $unavailabilityReason, $travelTimeMinutes, $isPracticalTest);

        app(InstructorCalendarService::class)->invalidateCalendarCache($instructor->id, $date);

        $this->checkAndNotifyClashes($instructor, $item, $date, $startTime, $endTime);

        return $item;
    }

    /**
     * Update a calendar item (time slot) - handles moves and status changes.
     *
     * @param  Instructor  $instructor  The instructor who owns the calendar
     * @param  CalendarItem  $calendarItem  The calendar item to update
     * @param  string  $date  New date in Y-m-d format
     * @param  string  $startTime  New start time in H:i format
     * @param  string  $endTime  New end time in H:i format
     * @param  bool|null  $isAvailable  New availability status (null to keep unchanged)
     * @param  string|null  $notes  Notes about the slot (null to keep unchanged)
     * @param  string|null  $unavailabilityReason  Reason for unavailability (null to keep unchanged)
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (0 to remove, 15/30/45 to set)
     * @return CalendarItem The updated calendar item
     */
    public function updateCalendarItem(
        Instructor $instructor,
        CalendarItem $calendarItem,
        string $date,
        string $startTime,
        string $endTime,
        ?bool $isAvailable = null,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
    ): CalendarItem {
        // Capture old values before the update for student notifications
        $oldDate = $calendarItem->calendar?->date;
        $oldDateString = $oldDate?->format('Y-m-d');
        $oldStartTime = $calendarItem->start_time;
        $oldEndTime = $calendarItem->end_time;
        $hasTimeChanged = $oldDateString !== $date || $oldStartTime !== $startTime || $oldEndTime !== $endTime;

        // Load lessons with students before updating
        $affectedLessons = $hasTimeChanged
            ? $calendarItem->lessons()->with(['order.student.user'])->get()
            : collect();

        $item = ($this->updateCalendarItem)($instructor, $calendarItem, $date, $startTime, $endTime, $isAvailable, $notes, $unavailabilityReason, $travelTimeMinutes);

        $calendarService = app(InstructorCalendarService::class);
        $calendarService->invalidateCalendarCache($instructor->id, $date);

        if ($oldDate && $oldDateString !== $date) {
            $calendarService->invalidateCalendarCache($instructor->id, $oldDateString);
        }

        // Notify students if the time/date has changed and there are booked lessons
        if ($hasTimeChanged && $affectedLessons->isNotEmpty()) {
            $itemNotes = $item->notes ?? $notes;
            $this->notifyStudentsOfReschedule(
                $instructor,
                $affectedLessons,
                $oldDateString,
                $oldStartTime,
                $oldEndTime,
                $itemNotes
            );
        }

        return $item;
    }

    /**
     * Remove a calendar item (time slot).
     *
     * @return bool Whether the deletion was successful
     */
    public function removeCalendarItem(CalendarItem $calendarItem): bool
    {
        $instructorId = $calendarItem->calendar?->instructor_id;
        $date = $calendarItem->calendar?->date;

        $result = ($this->deleteCalendarItem)($calendarItem);

        if ($instructorId && $date) {
            app(InstructorCalendarService::class)->invalidateCalendarCache($instructorId, $date);
        }

        return $result;
    }

    /**
     * Create recurring calendar items for an instructor.
     *
     * @return Collection The created calendar items
     */
    public function addRecurringCalendarItems(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime,
        RecurrencePattern $pattern,
        ?string $recurrenceEndDate = null,
        bool $isAvailable = true,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
    ): Collection {
        $items = ($this->createRecurringCalendarItems)(
            $instructor,
            $date,
            $startTime,
            $endTime,
            $pattern,
            $recurrenceEndDate,
            $isAvailable,
            $notes,
            $unavailabilityReason,
            $travelTimeMinutes
        );

        foreach ($items as $item) {
            $itemDate = $item->calendar?->date?->format('Y-m-d') ?? $date;
            $this->checkAndNotifyClashes($instructor, $item, $itemDate, $startTime, $endTime);
        }

        return $items;
    }

    /**
     * Delete recurring calendar items from a given item forward.
     *
     * @return int Number of items deleted
     */
    public function removeRecurringCalendarItems(CalendarItem $calendarItem): int
    {
        return ($this->deleteRecurringCalendarItems)($calendarItem);
    }

    /**
     * Check for clashes with existing calendar items and notify the instructor.
     */
    protected function checkAndNotifyClashes(
        Instructor $instructor,
        CalendarItem $newItem,
        string $date,
        string $startTime,
        string $endTime
    ): void {
        $clashes = ($this->detectCalendarClashes)($instructor, $date, $startTime, $endTime, $newItem->id);

        if ($clashes->isEmpty()) {
            return;
        }

        $instructor->load('user');
        $newItem->load('calendar');

        $instructor->user->notify(new CalendarClashDetectedNotification($newItem, $clashes, $instructor));

        ($this->logActivity)(
            $instructor,
            'Scheduling clash detected on '.Carbon::parse($date)->format('j M Y').' at '.$startTime.' — '.$clashes->count().' conflicting item(s)',
            'notification',
            [
                'new_item_id' => $newItem->id,
                'clashing_item_ids' => $clashes->pluck('id')->toArray(),
                'date' => $date,
            ]
        );
    }

    /**
     * Notify students whose lessons have been rescheduled due to a calendar item move.
     */
    protected function notifyStudentsOfReschedule(
        Instructor $instructor,
        Collection $lessons,
        string $oldDate,
        string $oldStartTime,
        string $oldEndTime,
        ?string $notes = null
    ): void {
        $instructor->load('user');

        foreach ($lessons as $lesson) {
            $student = $lesson->order?->student;
            $user = $student?->user;

            if (! $student || ! $user) {
                continue;
            }

            // Update the lesson's date/time to match the moved calendar item
            $lesson->refresh();

            $user->notify(new LessonRescheduledNotification(
                $lesson,
                $student,
                $instructor,
                $oldDate,
                $oldStartTime,
                $oldEndTime,
                $notes
            ));

            ($this->logActivity)(
                $student,
                'Lesson rescheduled by '.$instructor->user->name.' from '.Carbon::parse($oldDate)->format('j M Y').' '.$oldStartTime,
                'notification',
                [
                    'lesson_id' => $lesson->id,
                    'instructor_id' => $instructor->id,
                    'old_date' => $oldDate,
                    'old_start_time' => $oldStartTime,
                    'old_end_time' => $oldEndTime,
                ]
            );
        }
    }

    /**
     * Get instructor's students grouped by status with recent activity.
     *
     * @return array{active: Collection, passed: Collection, inactive: Collection, recent_activity: Collection}
     */
    public function getGroupedStudents(Instructor $instructor): array
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'grouped_students');

        return $this->remember($key, fn () => ($this->getGroupedStudents)($instructor));
    }

    /**
     * Get the instructor's lessons for a specific date.
     *
     * @return Collection Lessons with student, calendar item, and payment data
     */
    public function getDayLessons(Instructor $instructor, string $date): Collection
    {
        return ($this->getInstructorDayLessons)($instructor, $date);
    }

    /**
     * Invalidate cached student data for an instructor.
     */
    public function invalidateStudentCache(Instructor $instructor): void
    {
        $this->invalidate(
            $this->cacheKey('instructor', $instructor->id, 'grouped_students')
        );
    }

    /**
     * Get all students (pupils) belonging to an instructor.
     *
     * @param  string|null  $search  Optional search term
     * @param  string  $status  Filter by student status ('active' by default, 'all' to show everyone)
     * @return Collection Formatted pupil data
     */
    public function getPupils(Instructor $instructor, ?string $search = null, string $status = 'active'): Collection
    {
        return ($this->getInstructorPupils)($instructor, $search, $status);
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

        $this->invalidateStudentCache($instructor);

        ($this->logActivity)(
            $instructor,
            'Pupil '.$data['first_name'].' '.$data['surname'].' added',
            'student',
            ['student_id' => $student->id, 'email' => $data['email']]
        );

        return $student;
    }

    /**
     * Get all payouts for an instructor.
     *
     * @return Collection Formatted payout data
     */
    public function getPayouts(Instructor $instructor): Collection
    {
        return ($this->getInstructorPayouts)($instructor);
    }

    /**
     * Update an instructor's profile with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(Instructor $instructor, array $data): Instructor
    {
        return ($this->updateInstructorProfile)($instructor, $data);
    }

    /**
     * Upload or replace an instructor's profile picture.
     */
    public function updateProfilePicture(Instructor $instructor, UploadedFile $file): Instructor
    {
        return ($this->uploadProfilePicture)($instructor, $file);
    }

    /**
     * Delete an instructor's profile picture.
     */
    public function deleteProfilePicture(Instructor $instructor): Instructor
    {
        return ($this->deleteProfilePicture)($instructor);
    }

    /**
     * Log that the instructor is on their way to a lesson.
     *
     * TODO: Replace with actual push notification to student when push is implemented.
     */
    public function notifyStudentOnWay(Instructor $instructor, Lesson $lesson): void
    {
        $student = $lesson->order?->student;

        ($this->logActivity)(
            $instructor,
            'Instructor is on their way to lesson #'.$lesson->id.($student ? ' with '.$student->first_name.' '.$student->surname : ''),
            'lesson',
            [
                'lesson_id' => $lesson->id,
                'student_id' => $student?->id,
                'notification_type' => 'on_way',
            ]
        );
    }

    /**
     * Log that the instructor has arrived at a lesson.
     *
     * TODO: Replace with actual push notification to student when push is implemented.
     */
    public function notifyStudentArrived(Instructor $instructor, Lesson $lesson): void
    {
        $student = $lesson->order?->student;

        ($this->logActivity)(
            $instructor,
            'Instructor has arrived for lesson #'.$lesson->id.($student ? ' with '.$student->first_name.' '.$student->surname : ''),
            'lesson',
            [
                'lesson_id' => $lesson->id,
                'student_id' => $student?->id,
                'notification_type' => 'arrived',
            ]
        );
    }

    /**
     * Bulk import instructors from parsed CSV rows.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function bulkImportInstructors(array $rows): array
    {
        return ($this->bulkImportInstructors)($rows);
    }

    /**
     * Parse a CSV file into an array of associative rows.
     *
     * @return array<int, array<string, string>>
     */
    public function parseCsvFile(\Illuminate\Http\UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, fn ($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), ''));
        }

        fclose($handle);

        return $rows;
    }
}
