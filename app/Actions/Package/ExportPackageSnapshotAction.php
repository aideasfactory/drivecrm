<?php

declare(strict_types=1);

namespace App\Actions\Package;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reads a package and every row the database cascade removes with it (orders, lessons,
 * lesson payments, payouts, reminders, reflective logs, lesson resources, slot offers),
 * plus the lessons' diary slots and the pupils' test pass guarantee links, as raw rows.
 * Read-only. The result is what RestorePackageSnapshotAction puts back.
 */
class ExportPackageSnapshotAction
{
    public const VERSION = 1;

    /**
     * Child tables keyed by lesson_id, in the order they must be restored.
     *
     * @var list<string>
     */
    public const LESSON_CHILD_TABLES = ['lesson_payments', 'payouts', 'lesson_reminders', 'lesson_resource', 'reflective_logs'];

    /**
     * @return array{
     *     version: int,
     *     package_id: int,
     *     exported_at: string,
     *     source_database: string,
     *     tables: array<string, list<array<string, mixed>>>,
     *     guarantee_links: list<array{student_id: int, order_id: int}>
     * }
     */
    public function __invoke(int $packageId): array
    {
        $package = DB::table('packages')->where('id', $packageId)->first();

        if ($package === null) {
            throw new RuntimeException("Package {$packageId} not found in this database.");
        }

        $orders = $this->rows('orders', 'package_id', [$packageId]);
        $lessons = $this->rows('lessons', 'order_id', array_column($orders, 'id'));
        $lessonIds = array_column($lessons, 'id');
        $calendarItemIds = array_values(array_unique(array_filter(array_column($lessons, 'calendar_item_id'))));

        $tables = [
            'packages' => [(array) $package],
            'calendar_items' => $this->rows('calendar_items', 'id', $calendarItemIds),
            'orders' => $orders,
            'lessons' => $lessons,
        ];

        foreach (self::LESSON_CHILD_TABLES as $table) {
            $tables[$table] = $this->rows($table, 'lesson_id', $lessonIds);
        }

        $tables['slot_offers'] = $this->rows('slot_offers', 'package_id', [$packageId]);

        $guaranteeLinks = DB::table('students')
            ->whereIn('test_pass_guarantee_order_id', array_column($orders, 'id') ?: [0])
            ->orderBy('id')
            ->get(['id', 'test_pass_guarantee_order_id'])
            ->map(fn (object $student): array => [
                'student_id' => (int) $student->id,
                'order_id' => (int) $student->test_pass_guarantee_order_id,
            ])
            ->all();

        return [
            'version' => self::VERSION,
            'package_id' => $packageId,
            'exported_at' => now()->toDateTimeString(),
            'source_database' => DB::getDatabaseName(),
            'tables' => $tables,
            'guarantee_links' => $guaranteeLinks,
        ];
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
