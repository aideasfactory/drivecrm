<?php

declare(strict_types=1);

namespace App\Actions\Package;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Puts back a package snapshot (see ExportPackageSnapshotAction) with every original id, so
 * Stripe metadata that references order / lesson ids still matches.
 *
 * Insert-only for rows that are missing; a row that is already there is never overwritten,
 * except a diary slot that no other lesson uses, which is set back to its snapshot values.
 * Writes go through the query builder: no model events, emails, Stripe calls or jobs.
 *
 * Refuses to write anything when: a column in the snapshot doesn't exist here, an id is
 * already used by a different record, or a diary slot is now used by another lesson.
 * One transaction; a dry run does the whole restore and rolls it back.
 */
class RestorePackageSnapshotAction
{
    /**
     * Restore order (parents before children) and the column that ties each row to its
     * parent, used to tell "already restored" apart from "id taken by something else".
     *
     * @var array<string, string|null>
     */
    private const TABLES = [
        'packages' => null,
        'calendar_items' => 'calendar_id',
        'orders' => 'package_id',
        'lessons' => 'order_id',
        'lesson_payments' => 'lesson_id',
        'payouts' => 'lesson_id',
        'lesson_reminders' => 'lesson_id',
        'lesson_resource' => 'lesson_id',
        'reflective_logs' => 'lesson_id',
        'slot_offers' => 'package_id',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     tables: array<string, array{in_snapshot: int, already_present: int, inserted: int}>,
     *     slots_reset: int,
     *     guarantee_links_restored: int,
     *     problems: list<string>,
     *     committed: bool
     * }
     */
    public function __invoke(array $snapshot, bool $dryRun): array
    {
        $this->assertSnapshotShape($snapshot);

        /** @var array<string, list<array<string, mixed>>> $tables */
        $tables = $snapshot['tables'];
        $report = [
            'tables' => [],
            'slots_reset' => 0,
            'guarantee_links_restored' => 0,
            'problems' => $this->findProblems($tables),
            'committed' => false,
        ];

        if ($report['problems'] !== []) {
            return $report;
        }

        DB::beginTransaction();

        try {
            foreach (array_keys(self::TABLES) as $table) {
                $report['tables'][$table] = $this->insertMissing($table, $tables[$table] ?? []);
            }

            $report['slots_reset'] = $this->resetSlots($tables['calendar_items'] ?? []);
            $report['guarantee_links_restored'] = $this->restoreGuaranteeLinks($snapshot['guarantee_links'] ?? []);

            $this->assertEverythingPresent($tables);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
            $report['committed'] = true;
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function assertSnapshotShape(array $snapshot): void
    {
        if (($snapshot['version'] ?? null) !== ExportPackageSnapshotAction::VERSION || ! is_array($snapshot['tables'] ?? null)) {
            throw new RuntimeException('Not a package snapshot this version of the app can read.');
        }

        $unknown = array_diff(array_keys($snapshot['tables']), array_keys(self::TABLES));

        if ($unknown !== []) {
            throw new RuntimeException('Snapshot has unexpected tables: '.implode(', ', $unknown));
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return list<string>
     */
    private function findProblems(array $tables): array
    {
        $problems = [];

        foreach (self::TABLES as $table => $parentColumn) {
            $rows = $tables[$table] ?? [];

            if ($rows === []) {
                continue;
            }

            $missingColumns = array_diff(array_keys($rows[0]), Schema::getColumnListing($table));

            if ($missingColumns !== []) {
                $problems[] = "{$table}: columns missing in this database: ".implode(', ', $missingColumns);
            }

            if ($parentColumn === null) {
                continue;
            }

            $snapshotParents = array_column($rows, $parentColumn, 'id');

            foreach (array_chunk(array_keys($snapshotParents), 500) as $ids) {
                $existing = DB::table($table)->whereIn('id', $ids)->pluck($parentColumn, 'id');

                foreach ($existing as $id => $parent) {
                    if ((string) $parent !== (string) $snapshotParents[$id]) {
                        $problems[] = "{$table} #{$id} already exists here but belongs to {$parentColumn} {$parent}, not {$snapshotParents[$id]}.";
                    }
                }
            }
        }

        $lessonIds = array_column($tables['lessons'] ?? [], 'id');
        $slotIds = array_column($tables['calendar_items'] ?? [], 'id');

        if ($slotIds !== []) {
            DB::table('lessons')
                ->whereIn('calendar_item_id', $slotIds)
                ->when($lessonIds !== [], fn ($query) => $query->whereNotIn('id', $lessonIds))
                ->orderBy('calendar_item_id')
                ->get(['id', 'order_id', 'calendar_item_id', 'status', 'date', 'start_time'])
                ->each(function (object $lesson) use (&$problems): void {
                    $problems[] = "Diary slot #{$lesson->calendar_item_id} is now used by lesson #{$lesson->id} "
                        ."(order #{$lesson->order_id}, {$lesson->status}, {$lesson->date} {$lesson->start_time}).";
                });
        }

        return $problems;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{in_snapshot: int, already_present: int, inserted: int}
     */
    private function insertMissing(string $table, array $rows): array
    {
        $present = [];

        foreach (array_chunk(array_column($rows, 'id'), 500) as $ids) {
            $present += array_flip(DB::table($table)->whereIn('id', $ids)->pluck('id')->all());
        }

        $missing = array_values(array_filter($rows, fn (array $row): bool => ! isset($present[$row['id']])));

        foreach (array_chunk($missing, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return [
            'in_snapshot' => count($rows),
            'already_present' => count($rows) - count($missing),
            'inserted' => count($missing),
        ];
    }

    /**
     * Set diary slots back to their snapshot values. Only reached when no other lesson uses
     * any of them (findProblems refuses otherwise).
     *
     * @param  list<array<string, mixed>>  $slots
     */
    private function resetSlots(array $slots): int
    {
        $reset = 0;

        foreach ($slots as $slot) {
            $current = (array) DB::table('calendar_items')->where('id', $slot['id'])->first();
            $changes = array_filter(
                $slot,
                fn (mixed $value, string $column): bool => $column !== 'id' && $this->differs($current[$column] ?? null, $value),
                ARRAY_FILTER_USE_BOTH,
            );

            if ($changes !== []) {
                DB::table('calendar_items')->where('id', $slot['id'])->update($changes);
                $reset++;
            }
        }

        return $reset;
    }

    /**
     * @param  list<array{student_id: int, order_id: int}>  $links
     */
    private function restoreGuaranteeLinks(array $links): int
    {
        $restored = 0;

        foreach ($links as $link) {
            $restored += DB::table('students')
                ->where('id', $link['student_id'])
                ->whereNull('test_pass_guarantee_order_id')
                ->update(['test_pass_guarantee_order_id' => $link['order_id']]);
        }

        return $restored;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function assertEverythingPresent(array $tables): void
    {
        foreach ($tables as $table => $rows) {
            $ids = array_column($rows, 'id');
            $found = 0;

            foreach (array_chunk($ids, 500) as $chunk) {
                $found += DB::table($table)->whereIn('id', $chunk)->count();
            }

            if ($found !== count($ids)) {
                throw new RuntimeException("{$table}: expected ".count($ids)." rows after restore, found {$found}. Rolled back.");
            }
        }
    }

    private function differs(mixed $current, mixed $snapshot): bool
    {
        if ($current === null || $snapshot === null) {
            return $current !== $snapshot;
        }

        return (string) $current !== (string) $snapshot;
    }
}
