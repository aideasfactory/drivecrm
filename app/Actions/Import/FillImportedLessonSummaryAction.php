<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\Lesson;

class FillImportedLessonSummaryAction
{
    /**
     * Fill the summary of a lesson imported on an earlier run from its
     * diary.csv row, only while the lesson has no summary yet — one written
     * since (e.g. at sign-off) is never overwritten. Nothing else on the
     * lesson changes and nothing is sent.
     *
     * @param  array<string, string|int>  $row
     * @return bool Whether a summary was filled
     */
    public function __invoke(array $row): bool
    {
        $summary = self::summaryText($row);

        if ($summary === null) {
            return false;
        }

        $lessonId = ImportMapping::modelIdFor(ImportMapping::ENTITY_LESSON, trim((string) $row['diary_ref']));

        if ($lessonId === null) {
            return false;
        }

        return Lesson::query()
            ->whereKey($lessonId)
            ->where(fn ($query) => $query->whereNull('summary')->orWhere('summary', ''))
            ->update(['summary' => $summary, 'summary_from_import' => true]) > 0;
    }

    /**
     * How many diary rows would fill a summary on an already-imported lesson
     * (for the check step — writes nothing).
     *
     * @param  array<int, array<string, string|int>>  $rows
     */
    public function countFillable(array $rows): int
    {
        $refs = collect($rows)
            ->filter(fn (array $row) => self::summaryText($row) !== null)
            ->map(fn (array $row) => trim((string) ($row['diary_ref'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $count = 0;

        foreach ($refs->chunk(1000) as $chunk) {
            $lessonIds = ImportMapping::query()
                ->where('entity', ImportMapping::ENTITY_LESSON)
                ->whereIn('source_ref', $chunk->all())
                ->pluck('model_id');

            $count += Lesson::query()
                ->whereKey($lessonIds->all())
                ->where(fn ($query) => $query->whereNull('summary')->orWhere('summary', ''))
                ->count();
        }

        return $count;
    }

    /**
     * A diary row's lesson summary: `summary`, or `notes` when summary is blank.
     *
     * @param  array<string, string|int>  $row
     */
    public static function summaryText(array $row): ?string
    {
        foreach (['summary', 'notes'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
