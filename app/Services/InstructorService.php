<?php

namespace App\Services;

use App\Actions\Calendar\CancelBookingAction;
use App\Actions\Calendar\DetectCalendarClashesAction;
use App\Actions\FetchPostcodeCoordinatesAction;
use App\Actions\FindInstructorsByPostcodeSectorAction;
use App\Actions\Instructor\BulkImportInstructorsAction;
use App\Actions\Instructor\CreateCalendarItemAction;
use App\Actions\Instructor\CreateInstructorFinanceAction;
use App\Actions\Instructor\CreateInstructorLocationAction;
use App\Actions\Instructor\CreateInstructorPackageAction;
use App\Actions\Instructor\CreatePupilAction;
use App\Actions\Instructor\CreateRecurringCalendarItemsAction;
use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Actions\Instructor\DeleteFinanceReceiptAction;
use App\Actions\Instructor\DeleteInstructorFinanceAction;
use App\Actions\Instructor\DeleteInstructorLocationAction;
use App\Actions\Instructor\DeleteInstructorProfilePictureAction;
use App\Actions\Instructor\DeleteRecurringCalendarItemsAction;
use App\Actions\Instructor\GetGroupedStudentsAction;
use App\Actions\Instructor\GetInstructorCalendarAction;
use App\Actions\Instructor\GetInstructorDayLessonsAction;
use App\Actions\Instructor\GetInstructorFinancesAction;
use App\Actions\Instructor\GetInstructorLocationsAction;
use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Actions\Instructor\GetInstructorPayoutsAction;
use App\Actions\Instructor\GetInstructorPupilsAction;
use App\Actions\Instructor\Mileage\CreateMileageLogAction;
use App\Actions\Instructor\Mileage\DeleteMileageLogAction;
use App\Actions\Instructor\Mileage\GetMileageLogsAction;
use App\Actions\Instructor\Mileage\UpdateMileageLogAction;
use App\Actions\Instructor\ReplaceInstructorLocationsAction;
use App\Actions\Instructor\SendInstructorWelcomeEmailAction;
use App\Actions\Instructor\UpdateCalendarItemAction;
use App\Actions\Instructor\UpdateInstructorFinanceAction;
use App\Actions\Instructor\UpdateInstructorProfileAction;
use App\Actions\Instructor\UploadFinanceReceiptAction;
use App\Actions\Instructor\UploadInstructorProfilePictureAction;
use App\Actions\Lesson\UpdateLessonMileageAction;
use App\Actions\ProgressTracker\SeedInstructorProgressTrackerAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Shared\Message\SendBroadcastMessageAction;
use App\Enums\MessageType;
use App\Enums\RecurrencePattern;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\Location;
use App\Models\Message;
use App\Models\MileageLog;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CalendarClashDetectedNotification;
use App\Notifications\InstructorArrivedNotification;
use App\Notifications\InstructorOnWayNotification;
use App\Notifications\LessonRescheduledNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        protected ReplaceInstructorLocationsAction $replaceInstructorLocations,
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
        protected DetectCalendarClashesAction $detectCalendarClashes,
        protected UpdateLessonMileageAction $updateLessonMileage,
        protected GetInstructorFinancesAction $getInstructorFinances,
        protected CreateInstructorFinanceAction $createInstructorFinance,
        protected UpdateInstructorFinanceAction $updateInstructorFinance,
        protected DeleteInstructorFinanceAction $deleteInstructorFinance,
        protected UploadFinanceReceiptAction $uploadFinanceReceipt,
        protected DeleteFinanceReceiptAction $deleteFinanceReceipt,
        protected GetMileageLogsAction $getMileageLogs,
        protected CreateMileageLogAction $createMileageLog,
        protected UpdateMileageLogAction $updateMileageLog,
        protected DeleteMileageLogAction $deleteMileageLog,
        protected SeedInstructorProgressTrackerAction $seedInstructorProgressTracker,
        protected SendInstructorWelcomeEmailAction $sendInstructorWelcomeEmail,
        protected CancelBookingAction $cancelBooking,
    ) {}

    /**
     * Create a new instructor with user account and locations.
     *
     * Throws ValidationException for known recoverable failures (e.g. postcode
     * lookup) so the form-request pipeline surfaces a 422 + field error back to
     * the Inertia client. Unexpected exceptions bubble up and roll the
     * transaction back, hitting Laravel's default error handling.
     */
    public function createInstructor(array $data): Instructor
    {
        $coordinates = ($this->fetchPostcodeCoordinates)($data['postcode']);

        if (! $coordinates || ! $coordinates['latitude'] || ! $coordinates['longitude']) {
            throw ValidationException::withMessages([
                'postcode' => 'We could not find coordinates for that postcode. Please double-check it and try again.',
            ]);
        }

        DB::beginTransaction();

        try {
            // 2. Create user account.
            // The account is created with a cryptographically-random password the admin
            // never sees — the instructor sets their real password via the welcome email
            // setup link. `welcome_email_pending` is set so we can show admins if the
            // email never goes out, and `password_change_required` is a belt-and-braces
            // gate in case anyone bypasses the setup link.
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(48)),
                'password_change_required' => true,
                'welcome_email_pending' => true,
                'role' => UserRole::INSTRUCTOR,
            ]);

            $avatarNumber = rand(1, 5);

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

            if (! empty($data['locations']) && is_array($data['locations'])) {
                foreach ($data['locations'] as $postcodeSector) {
                    if (empty(trim($postcodeSector))) {
                        continue;
                    }

                    Location::create([
                        'instructor_id' => $instructor->id,
                        'postcode_sector' => strtoupper(trim($postcodeSector)),
                    ]);
                }
            }

            ($this->seedInstructorProgressTracker)($instructor);

            DB::commit();

            // Dispatch welcome email after the transaction commits — never inside, so
            // we don't email an instructor whose record was rolled back. The action
            // logs activity and toggles `welcome_email_pending` on success/failure.
            ($this->sendInstructorWelcomeEmail)($instructor);

            return $instructor->load('user', 'locations');
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Resend the welcome / password-setup email to an existing instructor.
     * Mirrors the action triggered on initial creation — useful when the original
     * email failed to send or the link expired before the instructor used it.
     */
    public function resendWelcomeEmail(Instructor $instructor): bool
    {
        return ($this->sendInstructorWelcomeEmail)($instructor);
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

        $date = Carbon::parse($nextSlot->calendar_date);
        $time = Carbon::parse($nextSlot->item_start_time);

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
    public function createPackage(Instructor $instructor, array $data): Package
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
     * Replace all coverage locations for an instructor from parsed CSV rows.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows keyed by header
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function replaceLocationsFromCsvRows(Instructor $instructor, array $rows): array
    {
        return ($this->replaceInstructorLocations)($instructor, $rows);
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
     * @param  int|null  $studentId  Student assigned to a practical test (carries the date to their checklist)
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
        bool $isPracticalTest = false,
        ?int $studentId = null
    ): CalendarItem {
        $item = ($this->createCalendarItem)($instructor, $date, $startTime, $endTime, $isAvailable, $notes, $unavailabilityReason, $travelTimeMinutes, $isPracticalTest, $studentId);

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
        $date = $calendarItem->calendar?->date?->format('Y-m-d');

        $result = ($this->deleteCalendarItem)($calendarItem);

        if ($instructorId && $date) {
            app(InstructorCalendarService::class)->invalidateCalendarCache($instructorId, $date);
        }

        return $result;
    }

    /**
     * Cancel the booking on a calendar item — the student has left / no longer
     * wants lessons. Marks the lesson(s) cancelled (kept for history), frees the
     * diary slot(s), stops future weekly invoices and notifies the student (plus
     * Head Office when a paid lesson needs a manual refund). Cache invalidation
     * is handled inside the action.
     *
     * @param  bool  $applyToFutureInOrder  Also cancel future un-signed-off lessons in the same order.
     * @return array{cancelled_count: int, refund_required_count: int}
     */
    public function cancelBooking(CalendarItem $calendarItem, string $reason, bool $applyToFutureInOrder, User $actor): array
    {
        return ($this->cancelBooking)($calendarItem, $reason, $applyToFutureInOrder, $actor);
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
        $calendarItem->load('calendar');
        $instructorId = $calendarItem->calendar?->instructor_id;

        // Collect all affected dates before deletion so we can bust the cache
        $affectedDates = collect();
        if ($calendarItem->recurrence_group_id) {
            $affectedDates = CalendarItem::where('recurrence_group_id', $calendarItem->recurrence_group_id)
                ->whereHas('calendar', fn ($q) => $q->where('date', '>=', $calendarItem->calendar->date))
                ->whereDoesntHave('lessons')
                ->with('calendar')
                ->get()
                ->pluck('calendar.date')
                ->unique();
        }

        $count = ($this->deleteRecurringCalendarItems)($calendarItem);

        if ($instructorId && $affectedDates->isNotEmpty()) {
            $calendarService = app(InstructorCalendarService::class);
            foreach ($affectedDates as $date) {
                $calendarService->invalidateCalendarCache($instructorId, $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date);
            }
        }

        return $count;
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
        $students = Student::where('instructor_id', $instructor->id)
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
     * @return Student The created student
     */
    public function addPupil(Instructor $instructor, array $data): Student
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
     * Notify the student that the instructor is on their way. When the student
     * has a user account this is just a typed message row — MessageObserver
     * fires the email, push, and activity log. Students without an account
     * can't have a message row (`to` is a user FK), so they fall back to a
     * direct email plus an inline activity log entry.
     */
    public function notifyStudentOnWay(Instructor $instructor, Lesson $lesson): void
    {
        $student = $lesson->order?->student;

        if ($student?->user_id) {
            Message::create([
                'from' => $instructor->user_id,
                'to' => $student->user_id,
                'message' => $instructor->name.' is on their way to your driving lesson.',
                'type' => MessageType::LESSON_ON_WAY,
                'meta' => ['lesson_id' => $lesson->id],
            ]);

            return;
        }

        if ($student) {
            $recipientEmail = $student->email ?: $student->contact_email;

            if ($recipientEmail) {
                Notification::route('mail', $recipientEmail)
                    ->notify(new InstructorOnWayNotification($lesson, $instructor, $student));
            }
        }

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
     * Notify the student that the instructor has arrived. When the student has
     * a user account this is just a typed message row — MessageObserver fires
     * the email, push, and activity log. Students without an account can't
     * have a message row (`to` is a user FK), so they fall back to a direct
     * email plus an inline activity log entry.
     */
    public function notifyStudentArrived(Instructor $instructor, Lesson $lesson): void
    {
        $student = $lesson->order?->student;

        if ($student?->user_id) {
            Message::create([
                'from' => $instructor->user_id,
                'to' => $student->user_id,
                'message' => $instructor->name.' has arrived for your driving lesson and is waiting for you.',
                'type' => MessageType::LESSON_ARRIVED,
                'meta' => ['lesson_id' => $lesson->id],
            ]);

            return;
        }

        if ($student) {
            $recipientEmail = $student->email ?: $student->contact_email;

            if ($recipientEmail) {
                Notification::route('mail', $recipientEmail)
                    ->notify(new InstructorArrivedNotification($lesson, $instructor, $student));
            }
        }

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
    public function parseCsvFile(UploadedFile $file): array
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

    /**
     * Update mileage for a completed lesson.
     */
    public function updateLessonMileage(Lesson $lesson, ?int $mileage): Lesson
    {
        return ($this->updateLessonMileage)($lesson, $mileage);
    }

    /**
     * Get all finance records for an instructor.
     */
    public function getFinances(Instructor $instructor): Collection
    {
        return ($this->getInstructorFinances)($instructor);
    }

    /**
     * Create a new finance record for an instructor.
     */
    public function createFinance(Instructor $instructor, array $data): InstructorFinance
    {
        return ($this->createInstructorFinance)($instructor, $data);
    }

    /**
     * Update an existing finance record.
     */
    public function updateFinance(InstructorFinance $finance, array $data): InstructorFinance
    {
        return ($this->updateInstructorFinance)($finance, $data);
    }

    /**
     * Delete a finance record.
     */
    public function deleteFinance(InstructorFinance $finance): bool
    {
        return ($this->deleteInstructorFinance)($finance);
    }

    /**
     * Upload (or replace) the receipt attached to a finance record.
     */
    public function uploadFinanceReceipt(InstructorFinance $finance, UploadedFile $file): InstructorFinance
    {
        return ($this->uploadFinanceReceipt)($finance, $file);
    }

    /**
     * Remove the receipt attached to a finance record.
     */
    public function deleteFinanceReceipt(InstructorFinance $finance): InstructorFinance
    {
        return ($this->deleteFinanceReceipt)($finance);
    }

    /**
     * Get all mileage log entries for an instructor.
     */
    public function getMileageLogs(Instructor $instructor): Collection
    {
        return ($this->getMileageLogs)($instructor);
    }

    /**
     * Create a mileage log entry for an instructor.
     */
    public function createMileageLog(Instructor $instructor, array $data): MileageLog
    {
        return ($this->createMileageLog)($instructor, $data);
    }

    /**
     * Update a mileage log entry.
     */
    public function updateMileageLog(MileageLog $log, array $data): MileageLog
    {
        return ($this->updateMileageLog)($log, $data);
    }

    /**
     * Delete a mileage log entry.
     */
    public function deleteMileageLog(MileageLog $log): bool
    {
        return ($this->deleteMileageLog)($log);
    }

    /**
     * Resolve the effective `[from, to]` for a finance/mileage query.
     * Defaults to the last 30 days (inclusive) when either bound is missing.
     *
     * @return array{from: Carbon, to: Carbon, default_applied: bool}
     */
    public function resolveFinanceDateRange(?string $from, ?string $to): array
    {
        $defaultApplied = ! $from || ! $to;

        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::today()->endOfDay();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : $toDate->copy()->subDays(29)->startOfDay();

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'default_applied' => $defaultApplied,
        ];
    }

    /**
     * Cursor-paginated finance records in a date range, optionally filtered by type.
     */
    public function getFinancesInRange(
        Instructor $instructor,
        ?string $from,
        ?string $to,
        ?string $type = null,
        int $perPage = 25
    ): CursorPaginator {
        $range = $this->resolveFinanceDateRange($from, $to);

        return $instructor->finances()
            ->whereBetween('date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * Cursor-paginated mileage logs in a date range.
     */
    public function getMileageLogsInRange(
        Instructor $instructor,
        ?string $from,
        ?string $to,
        int $perPage = 25
    ): CursorPaginator {
        $range = $this->resolveFinanceDateRange($from, $to);

        return $instructor->mileageLogs()
            ->whereBetween('date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * Full-range finances + mileage + stats for the overview screen.
     *
     * @return array{
     *     date_range: array{from: string, to: string, default_applied: bool},
     *     finances: Collection,
     *     mileage: Collection,
     *     stats: array<string, int|string>
     * }
     */
    public function getFinanceSummary(Instructor $instructor, ?string $from, ?string $to): array
    {
        $range = $this->resolveFinanceDateRange($from, $to);
        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();

        $finances = $instructor->finances()
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $mileage = $instructor->mileageLogs()
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $totalPayments = (int) $finances->where('type', 'payment')->sum('amount_pence');
        $totalExpenses = (int) $finances->where('type', 'expense')->sum('amount_pence');
        $businessMiles = (int) $mileage->where('type', 'business')->sum('miles');
        $personalMiles = (int) $mileage->where('type', 'personal')->sum('miles');

        return [
            'date_range' => [
                'from' => $fromDate,
                'to' => $toDate,
                'default_applied' => $range['default_applied'],
            ],
            'finances' => $finances,
            'mileage' => $mileage,
            'stats' => [
                'total_records' => $finances->count(),
                'total_payments_pence' => $totalPayments,
                'total_payments_formatted' => '£'.number_format($totalPayments / 100, 2),
                'total_expenses_pence' => $totalExpenses,
                'total_expenses_formatted' => '£'.number_format($totalExpenses / 100, 2),
                'net_balance_pence' => $totalPayments - $totalExpenses,
                'net_balance_formatted' => '£'.number_format(($totalPayments - $totalExpenses) / 100, 2),
                'total_trips' => $mileage->count(),
                'business_miles' => $businessMiles,
                'personal_miles' => $personalMiles,
                'total_miles' => $businessMiles + $personalMiles,
            ],
        ];
    }
}
