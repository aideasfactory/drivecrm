<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\ReflectiveLog;

class ImportStagedReflectiveLogsAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected ParseReflectiveLogTextAction $parseReflectiveLogText,
    ) {}

    /**
     * Stage 6 for one instructor ref (rows grouped by the pupil's
     * instructor): pupils' reflective logs into reflective_logs, one per
     * lesson. Only for lessons that are in Drive and have no log yet; empty
     * logs are skipped. Sections are mapped as: Achieved today →
     * what_i_learned, Feelings → what_went_well, Actions to take →
     * what_to_improve, Goals + Next lesson → additional_notes.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['logs_added' => 0, 'already_imported' => 0, 'skipped_empty' => 0, 'skipped_no_lesson' => 0, 'skipped_lesson_has_log' => 0];
        $rows = array_column($this->readStagedRows->all($run, 'reflective_logs', $instructorRef), 'row');

        $mapped = ImportMapping::modelIdsFor(ImportMapping::ENTITY_REFLECTIVE_LOG, array_column($rows, 'log_ref'));
        $lessonIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_LESSON, array_filter(array_column($rows, 'diary_ref')));

        foreach ($rows as $row) {
            $ref = (string) $row['log_ref'];

            if (isset($mapped[$ref])) {
                $counts['already_imported']++;

                continue;
            }

            $lessonId = $lessonIds[$row['diary_ref'] ?? ''] ?? null;

            if ($lessonId === null) {
                $counts['skipped_no_lesson']++;

                continue;
            }

            $sections = ($this->parseReflectiveLogText)((string) $row['text']);

            if (ParseReflectiveLogTextAction::isEmpty($sections)) {
                $counts['skipped_empty']++;

                continue;
            }

            if (ReflectiveLog::query()->where('lesson_id', $lessonId)->exists()) {
                $counts['skipped_lesson_has_log']++;

                continue;
            }

            $notes = array_filter([
                $sections['goals'] !== [] ? 'Goals: '.implode('; ', $sections['goals']) : null,
                $sections['next_lesson'] !== [] ? 'Next lesson: '.implode('; ', $sections['next_lesson']) : null,
            ]);

            $log = ReflectiveLog::create([
                'lesson_id' => $lessonId,
                'what_i_learned' => $this->text($sections['achieved']),
                'what_went_well' => $this->text($sections['feelings']),
                'what_to_improve' => $this->text($sections['actions']),
                'additional_notes' => $notes !== [] ? implode("\n", $notes) : null,
            ]);

            ImportMapping::record($run, ImportMapping::ENTITY_REFLECTIVE_LOG, $ref, $log->id);
            $counts['logs_added']++;
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function text(array $items): ?string
    {
        return $items !== [] ? implode("\n", $items) : null;
    }
}
