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
        ['lesson_payments', 'alternative_payment_confirmed_by'],
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
     *   profile archived. With `$takeEmail` the kept login then takes the retired login's email.
     *
     * With `$dryRun` the counts are worked out and nothing is written.
     *
     * @return array<string, mixed>
     */
    public function __invoke(Instructor $from, Instructor $into, bool $takeEmail, bool $dryRun): array
    {
        $from->loadMissing('user');
        $into->loadMissing('user');

        $ids = $this->collectIds($from, $into);
        $report = $this->report($from, $into, $ids, $takeEmail);

        if ($dryRun) {
            return $report;
        }

        DB::transaction(function () use ($from, $into, $ids, $report, $takeEmail): void {
            $this->mergeCalendars($from, $into, $ids['shared_days'], $ids['removable_slots'], $ids['emptied_days']);

            DB::table('orders')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_orders'])
                ->update(['instructor_id' => $into->id]);
            DB::table('lessons')->where('instructor_id', $from->id)->whereNotIn('id', $ids['pinned_lessons'])
                ->update(['instructor_id' => $into->id]);

            foreach (self::INSTRUCTOR_TABLES as $table) {
                DB::table($table)->where('instructor_id', $from->id)->update(['instructor_id' => $into->id]);
            }

            foreach (self::USER_COLUMNS as [$table, $column]) {
                DB::table($table)->where($column, $from->user_id)->update([$column => $into->user_id]);
            }

            $this->moveProgress($from, $ids['progress_map']);
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

            $fromEmail = $from->user->email;
            $fromEmailVerifiedAt = $from->user->email_verified_at;

            $this->retire($from, $report['retired_email']);

            if ($takeEmail) {
                $into->user->forceFill([
                    'email' => $fromEmail,
                    'email_verified_at' => $fromEmailVerifiedAt ?? $into->user->email_verified_at,
                ])->save();
            }
        });

        return $report;
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
     * Empty availability slots: open slots with no pupil, no lesson and no slot offer.
     */
    private function emptySlots(): Builder
    {
        return DB::table('calendar_items as own')
            ->where('own.item_type', 'slot')
            ->where('own.is_available', true)
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

    /**
     * Lock the old login and archive the old instructor profile. Its Stripe account id stays on the
     * archived profile with its payout history.
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
