<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MergeInstructorAccountsAction
{
    /**
     * Tables whose `instructor_id` is simply re-pointed from the old instructor to the kept one.
     *
     * @var array<int, string>
     */
    private const INSTRUCTOR_TABLES = [
        'students',
        'lessons',
        'orders',
        'packages',
        'instructor_finances',
        'mileage_logs',
        'locations',
        'slot_offers',
        'vehicles',
    ];

    /**
     * User-id columns (who did something) re-pointed from the old login to the kept one.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const USER_COLUMNS = [
        ['messages', 'from'],
        ['messages', 'to'],
        ['notes', 'user_id'],
        ['orders', 'booked_by_user_id'],
        ['lessons', 'banked_by_user_id'],
        ['lesson_payments', 'alternative_payment_confirmed_by'],
    ];

    /**
     * Move everything an instructor account owns onto another instructor account, then retire the old one.
     *
     * The kept account's own data, login, app sessions and Stripe Connect account are never changed.
     * Diary days that exist on both accounts are merged (the old day's slots move onto the kept day); the kept
     * account's empty availability slots (no pupil, lesson or slot offer) that an old slot now covers are removed
     * with their travel blocks, so nothing can be booked on top of an imported lesson.
     * The old account's progress categories are dropped (the caller guarantees no pupil progress uses them).
     * The old login is locked (email rewritten, password randomised, tokens and sessions removed) and the
     * old instructor profile archived. With `$dryRun` the counts are worked out and nothing is written.
     *
     * @return array{
     *     tables: array<string, int>,
     *     user_columns: array<string, int>,
     *     calendar_days_moved: int,
     *     calendar_days_merged: int,
     *     calendar_slots_moved_onto_existing_days: int,
     *     calendar_empty_slots_removed: int,
     *     calendar_slots_clashing: int,
     *     progress_categories_dropped: int,
     *     activity_logs: int,
     *     contacts: int,
     *     import_mappings: int,
     *     retired_email: string,
     * }
     */
    public function __invoke(Instructor $from, Instructor $into, bool $dryRun): array
    {
        $from->loadMissing('user');
        $into->loadMissing('user');

        $report = $this->plan($from, $into);

        if ($dryRun) {
            return $report;
        }

        DB::transaction(function () use ($from, $into, $report): void {
            $this->mergeCalendars($from, $into);

            foreach (self::INSTRUCTOR_TABLES as $table) {
                DB::table($table)->where('instructor_id', $from->id)->update(['instructor_id' => $into->id]);
            }

            foreach (self::USER_COLUMNS as [$table, $column]) {
                DB::table($table)->where($column, $from->user_id)->update([$column => $into->user_id]);
            }

            DB::table('progress_categories')->where('instructor_id', $from->id)->delete();

            $this->repointMorphs('activity_logs', 'loggable', $from, $into);
            $this->repointMorphs('contacts', 'contactable', $from, $into);

            DB::table('import_mappings')->where('entity', 'instructor')->where('model_id', $from->id)
                ->update(['model_id' => $into->id, 'updated_at' => now()]);

            $into->logActivity(
                "Merged instructor account #{$from->id} ({$from->user->email}) into this account",
                'instructor',
                ['from_instructor_id' => $from->id, 'from_user_id' => $from->user_id] + $report,
            );

            $this->retire($from, $report['retired_email']);
        });

        return $report;
    }

    /**
     * Work out what the merge will change, without changing anything.
     *
     * @return array<string, mixed>
     */
    private function plan(Instructor $from, Instructor $into): array
    {
        $tables = [];
        foreach (self::INSTRUCTOR_TABLES as $table) {
            $tables[$table] = DB::table($table)->where('instructor_id', $from->id)->count();
        }

        $userColumns = [];
        foreach (self::USER_COLUMNS as [$table, $column]) {
            $userColumns["{$table}.{$column}"] = DB::table($table)->where($column, $from->user_id)->count();
        }

        $sharedDays = $this->sharedCalendarDays($from, $into);
        $removableSlotIds = $this->emptyKeptSlotsUnderOldSlots($sharedDays);

        return [
            'tables' => $tables,
            'user_columns' => $userColumns,
            'calendar_days_moved' => DB::table('calendars')->where('instructor_id', $from->id)->count() - count($sharedDays),
            'calendar_days_merged' => count($sharedDays),
            'calendar_slots_moved_onto_existing_days' => DB::table('calendar_items')
                ->whereIn('calendar_id', array_keys($sharedDays))->count(),
            'calendar_empty_slots_removed' => count($removableSlotIds),
            'calendar_slots_clashing' => $this->countClashingSlots($sharedDays, $removableSlotIds),
            'progress_categories_dropped' => DB::table('progress_categories')->where('instructor_id', $from->id)->count(),
            'activity_logs' => $this->morphQuery('activity_logs', 'loggable', $from)->count(),
            'contacts' => $this->morphQuery('contacts', 'contactable', $from)->count(),
            'import_mappings' => DB::table('import_mappings')->where('entity', 'instructor')->where('model_id', $from->id)->count(),
            'retired_email' => "merged-instructor-{$from->id}@deleted.invalid",
        ];
    }

    /**
     * Diary days held by both accounts (calendars are unique per instructor + date).
     *
     * @return array<int, int> old calendar id => kept calendar id
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
     * The kept account's empty availability slots that an old-account slot overlaps on a shared day,
     * plus their travel blocks. Empty = open slot with no pupil, no lesson and no slot offer.
     *
     * @param  array<int, int>  $sharedDays
     * @return array<int, int> calendar item ids
     */
    private function emptyKeptSlotsUnderOldSlots(array $sharedDays): array
    {
        $slotIds = [];

        foreach ($sharedDays as $oldCalendarId => $keptCalendarId) {
            array_push($slotIds, ...DB::table('calendar_items as kept')
                ->where('kept.calendar_id', $keptCalendarId)
                ->where('kept.item_type', 'slot')
                ->where('kept.is_available', true)
                ->whereNull('kept.student_id')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('lessons')
                    ->whereColumn('lessons.calendar_item_id', 'kept.id'))
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('slot_offers')
                    ->whereColumn('slot_offers.calendar_item_id', 'kept.id'))
                ->whereExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('calendar_items as old')
                    ->where('old.calendar_id', $oldCalendarId)
                    ->whereColumn('old.start_time', '<', 'kept.end_time')
                    ->whereColumn('old.end_time', '>', 'kept.start_time'))
                ->pluck('kept.id')
                ->all());
        }

        if ($slotIds === []) {
            return [];
        }

        $travelIds = DB::table('calendar_items')
            ->where('item_type', 'travel')
            ->whereIn('parent_item_id', $slotIds)
            ->pluck('id')
            ->all();

        return array_merge($slotIds, $travelIds);
    }

    /**
     * Old-account slots that still overlap a kept-account item after the empty slots are removed
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
                ->whereExists(fn ($query) => $query->select(DB::raw(1))
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
     * Move the old account's diary onto the kept account, merging days both accounts already have.
     */
    private function mergeCalendars(Instructor $from, Instructor $into): void
    {
        $sharedDays = $this->sharedCalendarDays($from, $into);
        $removableSlotIds = $this->emptyKeptSlotsUnderOldSlots($sharedDays);

        if ($removableSlotIds !== []) {
            DB::table('calendar_items')->whereIn('id', $removableSlotIds)->where('item_type', 'travel')->delete();
            DB::table('calendar_items')->whereIn('id', $removableSlotIds)->delete();
        }

        foreach ($sharedDays as $oldCalendarId => $keptCalendarId) {
            DB::table('calendar_items')->where('calendar_id', $oldCalendarId)->update(['calendar_id' => $keptCalendarId]);
            DB::table('calendars')->where('id', $oldCalendarId)->delete();
        }

        DB::table('calendars')->where('instructor_id', $from->id)->update(['instructor_id' => $into->id]);
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

    /**
     * Lock the old login and archive the old instructor profile.
     */
    private function retire(Instructor $from, string $retiredEmail): void
    {
        /** @var User $user */
        $user = $from->user;

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
        ])->save();
    }
}
