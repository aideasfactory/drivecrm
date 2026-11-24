<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Lesson\FindDuplicateLessonsAction;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DuplicateLessonService extends BaseService
{
    public const CSV_COLUMNS = [
        'group_id', 'group_types', 'instructor_id', 'instructor_name', 'pupil_id', 'pupil_name',
        'lesson_id', 'order_id', 'order_payment_mode', 'date', 'start_time', 'end_time', 'status',
        'goroadie_ids', 'imported', 'import_run_ids', 'created_at', 'updated_at', 'drive_plus_changes',
        'lesson_payment_ids', 'lesson_payment_statuses', 'stripe_invoice_ids', 'stripe_charge_ids',
        'payout_id', 'stripe_transfer_id', 'order_stripe_ids', 'finance_ids', 'reflective_log_id',
        'calendar_item_id', 'decision', 'keep_lesson_id', 'reason', 'needs_manual_review', 'review_reasons',
    ];

    public function __construct(
        protected FindDuplicateLessonsAction $findDuplicateLessons,
    ) {}

    /**
     * Scan every instructor for duplicate lessons and write the review CSV
     * (lessons:find-duplicates). Read-only apart from the CSV file.
     *
     * @return array{path: string, counts: array<string, int>, groups: array<int, array<string, mixed>>}
     */
    public function findDuplicates(?string $csvPath = null): array
    {
        $result = ($this->findDuplicateLessons)();
        $path = $csvPath ?? storage_path('app/duplicate-lessons/duplicate-lessons-'.now()->format('Ymd-His').'.csv');

        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Can't write {$path}.");
        }

        fputcsv($handle, self::CSV_COLUMNS);

        foreach ($result['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                fputcsv($handle, array_map(fn (string $column): string => self::csvSafe((string) ($row[$column] ?? '')), self::CSV_COLUMNS));
            }
        }

        fclose($handle);

        return ['path' => $path, 'counts' => $result['counts'], 'groups' => $result['groups']];
    }

    /**
     * Stop spreadsheet apps running a cell as a formula (names and notes come
     * from user input): a leading =, +, - or @ gets a ' prefix.
     */
    protected static function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
