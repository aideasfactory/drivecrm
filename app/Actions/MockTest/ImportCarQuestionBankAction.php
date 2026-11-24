<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTestQuestion;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Makes the Car bank match the DVSA "Car (Cat B) QB" spreadsheet: every row is
 * created or updated by its item code and labelled Car (the shared AB
 * car/motorcycle questions were previously labelled Motorcycle). Rows not in the
 * spreadsheet are never deleted, and Car rows missing from it are reported.
 */
class ImportCarQuestionBankAction
{
    /**
     * Spreadsheet columns (0-based). Row 1 is the title, row 2 the headers:
     * Item, Topic, Current Answer, Stem, Mark Answer, OptionA-D, Expl text,
     * List Amendments, NI Exempt, Stem.gif, A-D.gif, Stem/A-D HiRes, XRefs.
     */
    private const COLUMNS = [
        'item_code' => 0,
        'topic' => 1,
        'correct_answer' => 2,
        'stem' => 3,
        'option_a' => 5,
        'option_b' => 6,
        'option_c' => 7,
        'option_d' => 8,
        'explanation' => 9,
        'stem_image' => 12,
        'option_a_image' => 13,
        'option_b_image' => 14,
        'option_c_image' => 15,
        'option_d_image' => 16,
    ];

    private const IMAGE_FIELDS = ['stem_image', 'option_a_image', 'option_b_image', 'option_c_image', 'option_d_image'];

    /**
     * @return array{rows: int, created: array<int, string>, updated: array<int, string>, recategorised: array<int, string>, unchanged: int, invalid: array<int, string>, car_not_in_sheet: array<int, string>}
     */
    public function __invoke(string $path, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Spreadsheet not found: {$path}");
        }

        $sheetQuestions = $this->readSheet($path);

        $result = [
            'rows' => count($sheetQuestions),
            'created' => [],
            'updated' => [],
            'recategorised' => [],
            'unchanged' => 0,
            'invalid' => [],
            'car_not_in_sheet' => [],
        ];

        DB::transaction(function () use ($sheetQuestions, $dryRun, &$result): void {
            $existing = MockTestQuestion::query()
                ->whereIn('item_code', array_keys($sheetQuestions))
                ->get()
                ->keyBy('item_code');

            foreach ($sheetQuestions as $itemCode => $attributes) {
                if (! in_array($attributes['correct_answer'], ['A', 'B', 'C', 'D'], true) || $attributes['stem'] === null) {
                    $result['invalid'][] = $itemCode;

                    continue;
                }

                $question = $existing->get($itemCode) ?? new MockTestQuestion(['item_code' => $itemCode]);
                $previousCategory = $question->category;
                $question->fill($attributes + ['category' => MockTestQuestion::BANK_CATEGORY]);

                if (! $question->exists) {
                    $result['created'][] = $itemCode;
                } elseif ($question->isDirty()) {
                    $result['updated'][] = $itemCode;

                    if ($previousCategory !== MockTestQuestion::BANK_CATEGORY) {
                        $result['recategorised'][] = "{$itemCode} ({$previousCategory})";
                    }
                } else {
                    $result['unchanged']++;
                }

                if (! $dryRun) {
                    $question->save();
                }
            }

            $result['car_not_in_sheet'] = MockTestQuestion::query()
                ->inBank()
                ->whereNotIn('item_code', array_keys($sheetQuestions))
                ->pluck('item_code')
                ->all();
        });

        return $result;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function readSheet(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $questions = [];

        foreach (array_slice($rows, 2) as $row) {
            $itemCode = $this->clean($row[self::COLUMNS['item_code']] ?? null);

            if ($itemCode === null) {
                continue;
            }

            $attributes = [];

            foreach (self::COLUMNS as $field => $index) {
                if ($field === 'item_code') {
                    continue;
                }

                $value = $this->clean($row[$index] ?? null);

                $attributes[$field] = match (true) {
                    $value === null => null,
                    $field === 'correct_answer' => strtoupper($value),
                    in_array($field, self::IMAGE_FIELDS, true) => basename($value),
                    default => $value,
                };
            }

            $attributes['topic'] ??= 'General';
            $questions[$itemCode] = $attributes;
        }

        return $questions;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
