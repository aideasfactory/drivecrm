<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MergeInstructorAccountsAction
{
    /**
     * Tables whose `instructor_id` is simply re-pointed from the retired instructor to the kept one.
     * Orders and lessons are handled separately (anything tied to a payout stays behind).
     *
     * @var array<int, string>
     */
    private const INSTRUCTOR_TABLES = [
        'students',
        'packages',
        'instructor_finances',
        'mileage_logs',
        'locations',
        'slot_offers',
        'vehicles',
        'student_tests',
        'lesson_deletion_requests',
    ];

    /**
     * User-id columns (who did something) re-pointed from the retired login to the kept one.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const USER_COLUMNS = [
        ['messages', 'from'],
        ['messages', 'to'],
        ['notes', 'user_id'],
        ['orders', 'booked_by_user_id'],
        ['lessons', 'banked_by_user_id'],
        ['lessons', 'cancelled_by_user_id'],
        ['lesson_payments', 'alternative_payment_confirmed_by'],
        ['lesson_deletion_requests', 'requested_by_user_id'],
        ['lesson_deletion_requests', 'decided_by_user_id'],
    ];

    /**
     * User-id columns under a unique (column, partner) index: re-pointed where the kept login has no row
     * for the same partner yet, otherwise the retired login's duplicate row is removed.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const UNIQUE_USER_COLUMNS = [
        ['support_ticket_archives', 'participant_id', 'owner_id'],
        ['support_ticket_archives', 'owner_id', 'participant_id'],
        ['resource_watches', 'user_id', 'resource_id'],
    ];

    /**
     * Move everything an instructor account owns onto another instructor account, then retire the old one.
     *
     * - The kept account's data, app sessions and Stripe Connect account are never changed.
     * - Payout history stays on the retired account: any order with a paid-out lesson stays there with all its
     *   lessons, and payouts are never moved (that money went to the retired account's Stripe account).
     * - Diary: the kept account's diary is the live one, so the retired account's empty availability slots
     *   (no pupil, lesson or slot offer) are all removed with their travel blocks and days left empty are dropped.
     *   Its remaining items (lessons, blocks) move across, merging into days both accounts hold; the kept
     *   account's empty slots under them are removed so nothing can be booked on top of a lesson.
     * - Pupil progress moves onto the kept tracker's subcategory with the same category + subcategory name
     *   (the caller guarantees every one maps); if both exist for a pupil the most recently updated score wins.
     * - The old login is locked (email rewritten, password randomised, tokens and sessions removed) and the old
     *   profile archived with a record of the merge (into whom, by whom, when, its original email).
     *   With `$takeEmail` the kept login then takes the retired login's email.
     * - Only foreign keys move. No amount, Stripe id or payment status is changed.
     *
     * With `$dryRun` the counts are worked out and nothing is written. Otherwise everything runs in one
     * transaction: both instructor rows and both user rows are locked (in id order) and reloaded, an
     * archived or already-merged account on either side is refused, then `$guardLocked` runs against the
     * locked models so the caller's checks see the state the merge will act on. Any exception rolls back.
     *
     * @param  (callable(Instructor, Instructor): void)|null  $guardLocked
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function __invoke(Instructor $from, Instructor $into, bool $takeEmail, bool $dryRun, ?User $mergedBy = null, ?callable $guardLocked = null): array
    {
        $from->loadMissing('user');
        $into->loadMissing('user');

        if ($dryRun) {
            return $this->report($from, $into, $this->collectIds($from, $into), $takeEmail);
        }

        [$report, $from, $into, $fromEmail, $intoEmail] = DB::transaction(function () use ($from, $into, $takeEmail, $mergedBy, $guardLocked): array {
            [$from, $into] = $this->lockAndReload($from, $into);

            if ($guardLocked !== null) {
                $guardLocked($from, $into);
            }

            $fromEmail = $from->user->email;
            $intoEmail = $into->user->email;

            $ids = $this->collectIds($from, $into);
            $report = $this->report($from, $into, $ids, $takeEmail);

            $this->mergeCalendars($from, $into, $ids['shared_days'], $ids['removable_slots'], $ids['emptied_days']);

            DB::table('orders')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_orders'])
                ->update(['instructor_id' => $into->id]);
            DB::table('lessons')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_lessons'])
                ->update(['instructor_id' => $into->id]);

            $this->dropDuplicateLocations($from, $into);

            foreach (self::INSTRUCTOR_TABLES as $table) {
                DB::table($table)->where('instructor_id', $from->id)->update(['instructor_id' => $into->id]);
            }

            foreach (self::USER_COLUMNS as [$table, $column]) {
                DB::table($table)->where($column, $from->user_id)->update([$column => $into->user_id]);
            }

            foreach (self::UNIQUE_USER_COLUMNS as [$table, $column, $partner]) {
                $this->repointUniqueUserColumn($table, $column, $partner, $from->user_id, $into->user_id);
            }

            $this->moveProgress($from, $ids['progress_map']);
            DB::table('progress_categories')->where('instructor_id', $from->id)->delete();

            $this->repointMorphs('activity_logs', 'loggable', $from, $into);
            $this->repointMorphs('contacts', 'contactable', $from, $into);
            $this->repointMorphs('notes', 'noteable', $from, $into);

            DB::table('import_mappings')->where('entity', 'instructor')->where('model_id', $from->id)
                ->update(['model_id' => $into->id, 'updated_at' => now()]);

            $fromEmailVerifiedAt = $from->user->email_verified_at;

            $this->retire($from, $into, $report['retired_email'], $mergedBy);

            if ($takeEmail) {
                $into->user->forceFill([
                    'email' => $fromEmail,
                    'email_verified_at' => $fromEmailVerifiedAt ?? $into->user->email_verified_at,
                ])->save();
            }

            return [$report, $from, $into, $fromEmail, $intoEmail];
        });

        $byline = $mergedBy ? " by {$mergedBy->name} (user #{$mergedBy->id})" : '';
        $metadata = [
            'from_instructor_id' => $from->id,
            'from_user_id' => $from->user_id,
            'from_email' => $fromEmail,
            'into_instructor_id' => $into->id,
            'into_user_id' => $into->user_id,
            'into_email_before_merge' => $intoEmail,
            'merged_by_user_id' => $mergedBy?->id,
        ] + $report;

        $into->logActivity(
            "Merged instructor account #{$from->id} ({$fromEmail}) into this account{$byline}",
            'instructor',
            $metadata,
            "Account #{$from->id} ({$fromEmail}) merged into this account",
        );
        $from->logActivity(
            "Merged this account into instructor account #{$into->id} ({$intoEmail}){$byline}; this account was then deactivated",
            'instructor',
            $metadata,
            "Merged into account #{$into->id} ({$intoEmail})",
        );

        return $report;
    }

    /**
     * Lock both instructor rows, then both user rows (each in id order so opposite-direction merges queue
     * rather than deadlock), and return fresh copies. Refuses when either side is archived or already merged.
     *
     * @return array{0: Instructor, 1: Instructor}
     *
     * @throws RuntimeException
     */
    private function lockAndReload(Instructor $from, Instructor $into): array
    {
        $instructors = Instructor::query()->whereKey([$from->id, $into->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedFrom = $instructors->get($from->id);
        $lockedInto = $instructors->get($into->id);

        if ($lockedFrom === null || $lockedInto === null || $lockedFrom->is($lockedInto)) {
            throw new RuntimeException('Refusing to merge: both accounts must exist and be different instructors.');
        }

        $users = User::query()->whereKey([$lockedFrom->user_id, $lockedInto->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedFrom->setRelation('user', $users->get($lockedFrom->user_id));
        $lockedInto->setRelation('user', $users->get($lockedInto->user_id));

        foreach (['old' => $lockedFrom, 'live' => $lockedInto] as $label => $instructor) {
            if ($instructor->user === null || $instructor->merged_at !== null || $instructor->status === InstructorStatus::Archived->value) {
                throw new RuntimeException("Refusing to merge: the {$label} account is archived or already merged.");
            }
        }

        return [$lockedFrom, $lockedInto];
    }

    /**
     * Postcode sectors both accounts cover: drop the retired account's copy (re-pointing its import
     * mapping to the kept row) so the kept account isn't listed twice for the same sector.
     */
    private function dropDuplicateLocations(Instructor $from, Instructor $into): void
    {
        $keptBySector = DB::table('locations')->where('instructor_id', $into->id)
            ->orderBy('id')->get(['id', 'postcode_sector'])
            ->unique(fn (object $row): string => mb_strtoupper(trim((string) $row->postcode_sector)))
            ->mapWithKeys(fn (object $row): array => [mb_strtoupper(trim((string) $row->postcode_sector)) => (int) $row->id]);

        foreach (DB::table('locations')->where('instructor_id', $from->id)->get(['id', 'postcode_sector']) as $row) {
            $keptId = $keptBySector->get(mb_strtoupper(trim((string) $row->postcode_sector)));

            if ($keptId === null) {
                continue;
            }

            DB::table('import_mappings')->where('entity', 'location')->where('model_id', $row->id)
                ->update(['model_id' => $keptId, 'updated_at' => now()]);
            DB::table('locations')->where('id', $row->id)->delete();
        }
    }

    /**
     * Work out which rows the merge touches.
     *
     * @return array{
     *     pinned_orders: array<int, int>,
     *     pinned_lessons: array<int, int>,
     *     shared_days: array<int, int>,
     *     removable_slots: array<int, int>,
     *     emptied_days: array<int, int>,
     *     progress_map: array<int, int|null>,
     * }
     */
    private function collectIds(Instructor $from, Instructor $into): array
    {
        $paidOutLessonIds = DB::table('payouts')->join('lessons', 'lessons.id', '=', 'payouts.lesson_id')
            ->where('lessons.instructor_id', $from->id)->pluck('lessons.id')->all();

        $pinnedOrderIds = DB::table('orders')->where('instructor_id', $from->id)
            ->whereIn('id', DB::table('lessons')->whereIn('id', $paidOutLessonIds)->whereNotNull('order_id')->select('order_id'))
            ->pluck('id')->all();

        $pinnedLessonIds = DB::table('lessons')->where('instructor_id', $from->id)
            ->where(fn (Builder $query) => $query->whereIn('id', $paidOutLessonIds)->orWhereIn('order_id', $pinnedOrderIds))
            ->pluck('id')->all();

        $sharedDays = $this->sharedCalendarDays($from, $into);
        $removableSlotIds = $this->removableSlots($from, $sharedDays);

        return [
            'pinned_orders' => $pinnedOrderIds,
            'pinned_lessons' => $pinnedLessonIds,
            'shared_days' => $sharedDays,
            'removable_slots' => $removableSlotIds,
            'emptied_days' => $this->emptiedRetiredDays($from, $removableSlotIds),
            'progress_map' => $this->progressSubcategoryMap($from, $into),
        ];
    }

    /**
     * @param  array<string, array<int, int>|array<int, int|null>>  $ids
     * @return array<string, mixed>
     */
    private function report(Instructor $from, Instructor $into, array $ids, bool $takeEmail): array
    {
        $movingOrders = DB::table('orders')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_orders']);
        $movingLessons = DB::table('lessons')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_lessons']);

        $tables = [
            'orders' => (clone $movingOrders)->count(),
            '  …of which paid through Stripe' => (clone $movingOrders)->whereNotNull('stripe_payment_intent_id')->count(),
            'lessons' => (clone $movingLessons)->count(),
            '  …of which have lesson payments' => DB::table('lesson_payments')
                ->whereIn('lesson_id', (clone $movingLessons)->select('id'))->distinct()->count('lesson_id'),
        ];
        foreach (self::INSTRUCTOR_TABLES as $table) {
            $tables[$table] = DB::table($table)->where('instructor_id', $from->id)->count();
        }
        foreach (self::USER_COLUMNS as [$table, $column]) {
            $tables["{$table}.{$column} (user)"] = DB::table($table)->where($column, $from->user_id)->count();
        }
        foreach (self::UNIQUE_USER_COLUMNS as [$table, $column]) {
            $tables["{$table}.{$column} (user)"] = DB::table($table)->where($column, $from->user_id)->count();
        }

        $sharedDays = $ids['shared_days'];

        return [
            'tables' => $tables,
            'staying_payouts' => DB::table('payouts')->where('instructor_id', $from->id)->count(),
            'staying_orders' => count($ids['pinned_orders']),
            'staying_lessons' => count($ids['pinned_lessons']),
            'calendar_days_moved' => DB::table('calendars')->where('instructor_id', $from->id)
                ->whereNotIn('id', array_keys($sharedDays))->whereNotIn('id', $ids['emptied_days'])->count(),
            'calendar_days_merged' => count(array_diff(array_keys($sharedDays), $ids['emptied_days'])),
            'calendar_days_dropped' => count($ids['emptied_days']),
            'calendar_items_moved' => DB::table('calendar_items')
                ->whereIn('calendar_id', DB::table('calendars')->where('instructor_id', $from->id)->select('id'))
                ->whereNotIn('id', $ids['removable_slots'])->count(),
            'calendar_empty_slots_removed' => count($ids['removable_slots']),
            'calendar_slots_clashing' => $this->countClashingSlots($sharedDays, $ids['removable_slots']),
            'progress_scores_moved' => $this->progressQuery($from)->count(),
            'progress_categories_dropped' => DB::table('progress_categories')->where('instructor_id', $from->id)->count(),
            'activity_logs' => $this->morphQuery('activity_logs', 'loggable', $from)->count(),
            'contacts' => $this->morphQuery('contacts', 'contactable', $from)->count(),
            'notes' => $this->morphQuery('notes', 'noteable', $from)->count(),
            'import_mappings' => DB::table('import_mappings')->where('entity', 'instructor')->where('model_id', $from->id)->count(),
            'retired_email' => "merged-instructor-{$from->id}@deleted.invalid",
            'kept_login_email' => $takeEmail ? $from->user->email : $into->user->email,
        ];
    }

    /**
     * Diary days held by both accounts (calendars are unique per instructor + date).
     *
     * @return array<int, int> retired calendar id => kept calendar id
     */
    private function sharedCalendarDays(Instructor $from, Instructor $into): array
    {
        return DB::table('calendars as old')
            ->join('calendars as kept', 'kept.date', '=', 'old.date')
            ->where('old.instructor_id', $from->id)
            ->where('kept.instructor_id', $into->id)
            ->select(['old.id as old_calendar_id', 'kept.id as kept_calendar_id'])
            ->pluck('kept_calendar_id', 'old_calendar_id')
            ->all();
    }

    /**
     * Empty availability slots (+ their travel blocks) to remove: every one on the retired account (the kept
     * account's diary is the live one, so stale availability is never carried over), then the kept account's
     * that overlap a retired item still standing on a shared day (a lesson or block moving across).
     *
     * @param  array<int, int>  $sharedDays
     * @return array<int, int> calendar item ids
     */
    private function removableSlots(Instructor $from, array $sharedDays): array
    {
        $removable = $this->withTravel($this->emptySlots()
            ->whereIn('own.calendar_id', DB::table('calendars')->where('instructor_id', $from->id)->select('id'))
            ->pluck('own.id')
            ->all());

        foreach ($sharedDays as $oldCalendarId => $keptCalendarId) {
            array_push($removable, ...$this->withTravel($this->emptySlots()
                ->where('own.calendar_id', $keptCalendarId)
                ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))
                    ->from('calendar_items as other')
                    ->where('other.calendar_id', $oldCalendarId)
                    ->whereNotIn('other.id', $removable)
                    ->whereColumn('other.start_time', '<', 'own.end_time')
                    ->whereColumn('other.end_time', '>', 'own.start_time'))
                ->pluck('own.id')
                ->all()));
        }

        return $removable;
    }

    /**
     * Empty availability slots: open slots with no booking status, pupil, lesson or slot offer
     * (CalendarItem::scopeEmptyAvailability plus the pupil and slot-offer conditions).
     */
    private function emptySlots(): Builder
    {
        return DB::table('calendar_items as own')
            ->where('own.item_type', 'slot')
            ->where('own.is_available', true)
            ->whereNull('own.status')
            ->whereNull('own.student_id')
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))->from('lessons')
                ->whereColumn('lessons.calendar_item_id', 'own.id'))
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))->from('slot_offers')
                ->whereColumn('slot_offers.calendar_item_id', 'own.id'));
    }

    /**
     * Retired-account diary days that have nothing left once its empty availability is removed.
     *
     * @param  array<int, int>  $removableSlotIds
     * @return array<int, int> calendar ids
     */
    private function emptiedRetiredDays(Instructor $from, array $removableSlotIds): array
    {
        return DB::table('calendars')
            ->where('instructor_id', $from->id)
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('calendar_items')
                ->whereColumn('calendar_items.calendar_id', 'calendars.id')
                ->whereNotIn('calendar_items.id', $removableSlotIds))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $slotIds
     * @return array<int, int>
     */
    private function withTravel(array $slotIds): array
    {
        if ($slotIds === []) {
            return [];
        }

        return array_merge($slotIds, DB::table('calendar_items')
            ->where('item_type', 'travel')
            ->whereIn('parent_item_id', $slotIds)
            ->pluck('id')
            ->all());
    }

    /**
     * Retired-account items that still overlap a kept-account item after the empty slots are removed
     * (reported only, never changed).
     *
     * @param  array<int, int>  $sharedDays
     * @param  array<int, int>  $removableSlotIds
     */
    private function countClashingSlots(array $sharedDays, array $removableSlotIds): int
    {
        $clashing = 0;

        foreach ($sharedDays as $oldCalendarId => $keptCalendarId) {
            $clashing += DB::table('calendar_items as old')
                ->where('old.calendar_id', $oldCalendarId)
                ->whereNotIn('old.id', $removableSlotIds)
                ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))
                    ->from('calendar_items as kept')
                    ->where('kept.calendar_id', $keptCalendarId)
                    ->whereNotIn('kept.id', $removableSlotIds)
                    ->whereColumn('kept.start_time', '<', 'old.end_time')
                    ->whereColumn('kept.end_time', '>', 'old.start_time'))
                ->count();
        }

        return $clashing;
    }

    /**
     * The retired-account diary items that would still overlap a kept-account item after the merge
     * (the same pairs `calendar_slots_clashing` counts), for an admin to resolve beforehand.
     *
     * @return array<int, array{date: string, retired_item_id: int, retired_type: string, retired_start: string, retired_end: string, kept_item_id: int, kept_type: string, kept_start: string, kept_end: string}>
     */
    public function clashingItems(Instructor $from, Instructor $into, int $limit = 50): array
    {
        $sharedDays = $this->sharedCalendarDays($from, $into);

        if ($sharedDays === []) {
            return [];
        }

        $removableSlotIds = $this->removableSlots($from, $sharedDays);

        return DB::table('calendar_items as old')
            ->join('calendars as day', 'day.id', '=', 'old.calendar_id')
            ->join('calendars as kept_day', function ($join) use ($into): void {
                $join->on('kept_day.date', '=', 'day.date')->where('kept_day.instructor_id', '=', $into->id);
            })
            ->join('calendar_items as kept', 'kept.calendar_id', '=', 'kept_day.id')
            ->whereIn('old.calendar_id', array_keys($sharedDays))
            ->whereNotIn('old.id', $removableSlotIds)
            ->whereNotIn('kept.id', $removableSlotIds)
            ->whereColumn('kept.start_time', '<', 'old.end_time')
            ->whereColumn('kept.end_time', '>', 'old.start_time')
            ->orderBy('day.date')
            ->orderBy('old.start_time')
            ->limit($limit)
            ->get([
                'day.date', 'old.id as retired_item_id', 'old.item_type as retired_type',
                'old.start_time as retired_start', 'old.end_time as retired_end',
                'kept.id as kept_item_id', 'kept.item_type as kept_type',
                'kept.start_time as kept_start', 'kept.end_time as kept_end',
            ])
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'retired_item_id' => (int) $row->retired_item_id,
                'retired_type' => (string) $row->retired_type,
                'retired_start' => (string) $row->retired_start,
                'retired_end' => (string) $row->retired_end,
                'kept_item_id' => (int) $row->kept_item_id,
                'kept_type' => (string) $row->kept_type,
                'kept_start' => (string) $row->kept_start,
                'kept_end' => (string) $row->kept_end,
            ])
            ->all();
    }

    /**
     * Move the retired account's diary onto the kept account, merging days both accounts already have.
     *
     * @param  array<int, int>  $sharedDays
     * @param  array<int, int>  $removableSlotIds
     * @param  array<int, int>  $emptiedDayIds
     */
    private function mergeCalendars(Instructor $from, Instructor $into, array $sharedDays, array $removableSlotIds, array $emptiedDayIds): void
    {
        if ($removableSlotIds !== []) {
            DB::table('calendar_items')->whereIn('id', $removableSlotIds)->where('item_type', 'travel')->delete();
            DB::table('calendar_items')->whereIn('id', $removableSlotIds)->delete();
        }

        if ($emptiedDayIds !== []) {
            DB::table('calendars')->whereIn('id', $emptiedDayIds)->delete();
        }

        foreach ($sharedDays as $oldCalendarId => $keptCalendarId) {
            DB::table('calendar_items')->where('calendar_id', $oldCalendarId)->update(['calendar_id' => $keptCalendarId]);
            DB::table('calendars')->where('id', $oldCalendarId)->delete();
        }

        DB::table('calendars')->where('instructor_id', $from->id)->update(['instructor_id' => $into->id]);
    }

    /**
     * Map each of the retired tracker's subcategories to the kept tracker's subcategory with the same
     * category name + subcategory name (null when there is no match).
     *
     * @return array<int, int|null> retired subcategory id => kept subcategory id
     */
    public function progressSubcategoryMap(Instructor $from, Instructor $into): array
    {
        $kept = DB::table('progress_subcategories as s')
            ->join('progress_categories as c', 'c.id', '=', 's.progress_category_id')
            ->where('c.instructor_id', $into->id)
            ->orderByRaw('s.deleted_at is not null')
            ->get(['s.id', 's.name', 'c.name as category']);

        $keptByName = [];
        foreach ($kept as $row) {
            $keptByName[mb_strtolower(trim($row->category)).'|'.mb_strtolower(trim($row->name))] ??= $row->id;
        }

        $map = [];
        foreach (DB::table('progress_subcategories as s')
            ->join('progress_categories as c', 'c.id', '=', 's.progress_category_id')
            ->where('c.instructor_id', $from->id)
            ->get(['s.id', 's.name', 'c.name as category']) as $row) {
            $map[$row->id] = $keptByName[mb_strtolower(trim($row->category)).'|'.mb_strtolower(trim($row->name))] ?? null;
        }

        return $map;
    }

    /**
     * Pupil progress scores recorded on the retired account's tracker.
     */
    public function progressQuery(Instructor $from): Builder
    {
        return DB::table('student_progress as p')
            ->join('progress_subcategories as s', 's.id', '=', 'p.progress_subcategory_id')
            ->join('progress_categories as c', 'c.id', '=', 's.progress_category_id')
            ->where('c.instructor_id', $from->id);
    }

    /**
     * Re-point each progress score onto the kept tracker; where the pupil already has a score there,
     * keep whichever was updated most recently.
     *
     * @param  array<int, int|null>  $progressMap
     */
    private function moveProgress(Instructor $from, array $progressMap): void
    {
        $rows = $this->progressQuery($from)
            ->get(['p.id', 'p.student_id', 'p.progress_subcategory_id', 'p.score', 'p.updated_at']);

        foreach ($rows as $row) {
            $targetId = $progressMap[$row->progress_subcategory_id];

            $existing = DB::table('student_progress')
                ->where('student_id', $row->student_id)
                ->where('progress_subcategory_id', $targetId)
                ->first();

            if ($existing === null) {
                DB::table('student_progress')->where('id', $row->id)->update(['progress_subcategory_id' => $targetId]);

                continue;
            }

            if ($row->updated_at > $existing->updated_at) {
                DB::table('student_progress')->where('id', $existing->id)
                    ->update(['score' => $row->score, 'updated_at' => $row->updated_at]);
            }

            DB::table('student_progress')->where('id', $row->id)->delete();
        }
    }

    private function morphQuery(string $table, string $morph, Instructor $instructor): Builder
    {
        return DB::table($table)
            ->where("{$morph}_type", $instructor->getMorphClass())
            ->where("{$morph}_id", $instructor->id);
    }

    private function repointMorphs(string $table, string $morph, Instructor $from, Instructor $into): void
    {
        $this->morphQuery($table, $morph, $from)->update(["{$morph}_id" => $into->id]);
    }

    private function repointUniqueUserColumn(string $table, string $column, string $partner, int $fromUserId, int $intoUserId): void
    {
        DB::table("{$table} as old")
            ->where("old.{$column}", $fromUserId)
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from("{$table} as kept")
                ->where("kept.{$column}", $intoUserId)
                ->whereColumn("kept.{$partner}", "old.{$partner}"))
            ->pluck('old.id')
            ->chunk(500)
            ->each(fn ($ids) => DB::table($table)->whereIn('id', $ids->all())->update([$column => $intoUserId]));

        DB::table($table)->where($column, $fromUserId)->delete();
    }

    /**
     * Lock the old login and archive the old instructor profile with a record of the merge. Its Stripe
     * account id stays on the archived profile with its payout history.
     */
    private function retire(Instructor $from, Instructor $into, string $retiredEmail, ?User $mergedBy): void
    {
        /** @var User $user */
        $user = $from->user;
        $originalEmail = $user->email;

        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill([
            'email' => $retiredEmail,
            'email_verified_at' => null,
            'password' => Str::random(64),
            'remember_token' => null,
            'expo_push_token' => null,
            'welcome_email_pending' => false,
        ])->save();

        $from->forceFill([
            'status' => InstructorStatus::Archived->value,
            'pin' => null,
            'merged_into_instructor_id' => $into->id,
            'merged_by_user_id' => $mergedBy?->id,
            'merged_at' => now(),
            'merged_original_email' => $originalEmail,
        ])->save();
    }
}
