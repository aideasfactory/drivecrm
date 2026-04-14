<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MockTestQuestion;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MockTestQuestionSeeder extends Seeder
{
    /**
     * Map of folder name → category name stored in DB.
     */
    private const CATEGORY_MAP = [
        'Car' => 'Car',
        'ADI' => 'ADI',
        'Motorcycle' => 'Motorcycle',
        'LGV-PCV' => 'LGV-PCV',
    ];

    /**
     * Column indexes (0-based) from the Excel files.
     * Row 2 is the header row: Item, Topic, Answer, Stem, Mark Answer,
     * OptionA, OptionB, OptionC, OptionD, Expl text, List Amendments,
     * NI Exempt, Stem.gif, A.gif, B.gif, C.gif, D.gif,
     * Stem HiRes, A HiRes, B HiRes, C HiRes, D HiRes, XRefs
     */
    private const COL_ITEM = 0;

    private const COL_TOPIC = 1;

    private const COL_ANSWER = 2;

    private const COL_STEM = 3;

    private const COL_OPTION_A = 5;

    private const COL_OPTION_B = 6;

    private const COL_OPTION_C = 7;

    private const COL_OPTION_D = 8;

    private const COL_EXPLANATION = 9;

    private const COL_STEM_GIF = 12;

    private const COL_A_GIF = 13;

    private const COL_B_GIF = 14;

    private const COL_C_GIF = 15;

    private const COL_D_GIF = 16;

    public function run(): void
    {
        $basePath = base_path('questions');

        foreach (self::CATEGORY_MAP as $folder => $category) {
            $filePath = "{$basePath}/{$folder}/{$folder}.xlsx";

            if (! file_exists($filePath)) {
                $this->command->warn("File not found: {$filePath} — skipping {$category}");

                continue;
            }

            $this->command->info("Importing {$category} from {$folder}/{$folder}.xlsx...");
            $this->importFile($filePath, $category);
        }
    }

    private function importFile(string $filePath, string $category): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $imported = 0;
        $skipped = 0;

        // Data starts at row index 2 (0-based: row 0 = title, row 1 = headers, row 2+ = data)
        for ($i = 2; $i < count($rows); $i++) {
            $row = $rows[$i];

            $itemCode = $this->clean($row[self::COL_ITEM] ?? null);
            $stem = $this->clean($row[self::COL_STEM] ?? null);

            if (! $itemCode || ! $stem) {
                $skipped++;

                continue;
            }

            $answer = strtoupper(trim((string) ($row[self::COL_ANSWER] ?? '')));

            if (! in_array($answer, ['A', 'B', 'C', 'D'])) {
                $this->command->warn("  Row {$i}: Invalid answer '{$answer}' for {$itemCode} — skipping");
                $skipped++;

                continue;
            }

            MockTestQuestion::updateOrCreate(
                ['item_code' => $itemCode],
                [
                    'category' => $category,
                    'topic' => trim((string) ($row[self::COL_TOPIC] ?? 'General')),
                    'stem' => $stem,
                    'option_a' => $this->clean($row[self::COL_OPTION_A] ?? null),
                    'option_b' => $this->clean($row[self::COL_OPTION_B] ?? null),
                    'option_c' => $this->clean($row[self::COL_OPTION_C] ?? null),
                    'option_d' => $this->clean($row[self::COL_OPTION_D] ?? null),
                    'correct_answer' => $answer,
                    'explanation' => $this->clean($row[self::COL_EXPLANATION] ?? null),
                    'stem_image' => $this->cleanImage($row[self::COL_STEM_GIF] ?? null),
                    'option_a_image' => $this->cleanImage($row[self::COL_A_GIF] ?? null),
                    'option_b_image' => $this->cleanImage($row[self::COL_B_GIF] ?? null),
                    'option_c_image' => $this->cleanImage($row[self::COL_C_GIF] ?? null),
                    'option_d_image' => $this->cleanImage($row[self::COL_D_GIF] ?? null),
                ]
            );

            $imported++;
        }

        $this->command->info("  ✓ {$category}: {$imported} imported, {$skipped} skipped");
    }

    private function clean(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function cleanImage(?string $value): ?string
    {
        $cleaned = $this->clean($value);

        if ($cleaned === null) {
            return null;
        }

        // Strip any path prefix, keep just the filename
        return basename($cleaned);
    }
}
