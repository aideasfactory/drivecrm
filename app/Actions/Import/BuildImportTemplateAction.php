<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\InstructorStatus;
use App\Enums\PdiStatus;
use App\Enums\TransmissionType;
use RuntimeException;
use ZipArchive;

class BuildImportTemplateAction
{
    /**
     * The columns each bundle CSV is read with, in template order, mirroring
     * ValidateImportBundleAction and the Import*Action classes. `true` marks a
     * column every row must fill.
     *
     * @var array<string, array<string, bool>>
     */
    public const COLUMNS = [
        'instructors' => [
            'instructor_ref' => true,
            'name' => true,
            'email' => true,
            'phone' => false,
            'address' => false,
            'postcode' => false,
            'bio' => false,
            'status' => false,
            'pdi_status' => false,
            'transmission_type' => false,
        ],
        'coverage' => [
            'instructor_ref' => true,
            'postcode_sector' => true,
        ],
        'students' => [
            'student_ref' => true,
            'instructor_ref' => true,
            'first_name' => true,
            'surname' => true,
            'email' => true,
            'phone' => false,
            'contact_first_name' => false,
            'contact_surname' => false,
            'contact_email' => false,
            'contact_phone' => false,
            'status' => false,
            'inactive_reason' => false,
        ],
        'diary' => [
            'diary_ref' => true,
            'instructor_ref' => true,
            'student_ref' => false,
            'date' => true,
            'start_time' => true,
            'end_time' => true,
            'status' => false,
            'price' => false,
            'mileage' => false,
            'summary' => false,
            'notes' => false,
        ],
        'finances' => [
            'finance_ref' => true,
            'instructor_ref' => true,
            'type' => true,
            'category' => false,
            'payment_method' => false,
            'date' => true,
            'amount' => true,
            'description' => true,
            'notes' => false,
            'receipt_file' => false,
        ],
    ];

    protected const EXAMPLE_RECEIPT = 'receipts/F-5002.pdf';

    /**
     * Build the export spec (README), example CSVs and an example receipt into
     * a temporary zip, ready to download and hand to whoever builds the export.
     * Everything is generated in memory, so the download does not depend on any
     * file being deployed. The example CSVs link to each other, so the zip is
     * itself a valid bundle.
     *
     * @return string Path to the temporary zip (caller deletes it after sending)
     *
     * @throws RuntimeException
     */
    public function __invoke(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'import-template-');

        if ($zipPath === false) {
            throw new RuntimeException('Could not create a temporary file for the template zip.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the template zip.');
        }

        $zip->addFromString('README.md', $this->readme());

        foreach ($this->exampleRows() as $section => $rows) {
            $zip->addFromString(ReadImportBundleAction::FILES[$section], $this->csv($section, $rows));
        }

        $zip->addFromString(self::EXAMPLE_RECEIPT, $this->examplePdf());

        if (! $zip->close()) {
            throw new RuntimeException('Could not write the template zip.');
        }

        return $zipPath;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    protected function csv(string $section, array $rows): string
    {
        $columns = array_keys(self::COLUMNS[$section]);
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $columns, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column) => $row[$column] ?? '', $columns), escape: '');
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Example rows for one instructor with a student, a past lesson, a booked
     * lesson, a blocked-out slot, coverage and two finance entries. Dates are
     * relative to today so the completed/booked rules always pass.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    protected function exampleRows(): array
    {
        $lastWeek = now()->subWeek()->toDateString();
        $nextWeek = now()->addWeek()->toDateString();

        return [
            'instructors' => [[
                'instructor_ref' => 'I-1001',
                'name' => 'Jane Example',
                'email' => 'jane.example@example.com',
                'phone' => '07700 900123',
                'address' => '1 Example Street, Edinburgh',
                'postcode' => 'EH12 5AB',
                'bio' => 'Patient, friendly instructor with 10 years of experience.',
                'status' => InstructorStatus::Active->value,
                'pdi_status' => PdiStatus::Qualified->value,
                'transmission_type' => TransmissionType::Manual->value,
            ]],
            'coverage' => [
                ['instructor_ref' => 'I-1001', 'postcode_sector' => 'EH12'],
                ['instructor_ref' => 'I-1001', 'postcode_sector' => 'EH4'],
            ],
            'students' => [[
                'student_ref' => 'S-2001',
                'instructor_ref' => 'I-1001',
                'first_name' => 'Sam',
                'surname' => 'Learner',
                'email' => 'sam.learner@example.com',
                'phone' => '07700 900456',
                'contact_first_name' => 'Pat',
                'contact_surname' => 'Learner',
                'contact_email' => 'pat.learner@example.com',
                'contact_phone' => '07700 900789',
                'status' => 'active',
            ]],
            'diary' => [
                [
                    'diary_ref' => 'D-3001',
                    'instructor_ref' => 'I-1001',
                    'student_ref' => 'S-2001',
                    'date' => $lastWeek,
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                    'status' => 'completed',
                    'price' => '35.00',
                    'mileage' => '12',
                    'summary' => 'Roundabouts and junctions.',
                ],
                [
                    'diary_ref' => 'D-3002',
                    'instructor_ref' => 'I-1001',
                    'student_ref' => 'S-2001',
                    'date' => $nextWeek,
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                    'status' => 'booked',
                    'price' => '35.00',
                ],
                [
                    'diary_ref' => 'D-3003',
                    'instructor_ref' => 'I-1001',
                    'date' => $nextWeek,
                    'start_time' => '13:00',
                    'end_time' => '14:00',
                    'notes' => 'Vehicle service',
                ],
            ],
            'finances' => [
                [
                    'finance_ref' => 'F-5001',
                    'instructor_ref' => 'I-1001',
                    'type' => 'payment',
                    'category' => 'referral',
                    'payment_method' => 'bank_transfer',
                    'date' => $lastWeek,
                    'amount' => '50.00',
                    'description' => 'Referral bonus',
                ],
                [
                    'finance_ref' => 'F-5002',
                    'instructor_ref' => 'I-1001',
                    'type' => 'expense',
                    'category' => 'fuel',
                    'payment_method' => 'card',
                    'date' => $lastWeek,
                    'amount' => '45.20',
                    'description' => 'Fuel',
                    'notes' => 'Shell, Corstorphine',
                    'receipt_file' => self::EXAMPLE_RECEIPT,
                ],
            ],
        ];
    }

    /**
     * Allowed values and formats per column, for the README.
     *
     * @return array<string, array<string, string>>
     */
    protected function columnNotes(): array
    {
        $list = fn (array $values): string => 'One of: '.implode(', ', $values).'.';
        $date = 'YYYY-MM-DD or DD/MM/YYYY.';

        return [
            'instructors' => [
                'instructor_ref' => 'Your unique ID for the instructor. Other files link to it.',
                'email' => 'Unique. An existing instructor with this email is linked, not duplicated.',
                'postcode' => 'Full postcode, used to place the instructor on the map.',
                'status' => $list(InstructorStatus::values()).' Default: active.',
                'pdi_status' => $list(PdiStatus::values()),
                'transmission_type' => $list(TransmissionType::values()).' Required for a new instructor.',
            ],
            'coverage' => [
                'postcode_sector' => 'Outward code the instructor covers, e.g. EH12. One row per sector.',
            ],
            'students' => [
                'student_ref' => 'Your unique ID for the student. diary.csv links to it.',
                'instructor_ref' => 'Must match an instructor in instructors.csv or an earlier import.',
                'email' => 'Unique, and must not belong to an existing account.',
                'contact_email' => 'Parent/guardian contact, optional.',
                'status' => $list(ValidateImportBundleAction::STUDENT_STATUSES).' Default: active.',
                'inactive_reason' => 'Only kept when status is not active.',
            ],
            'diary' => [
                'diary_ref' => 'Your unique ID for the diary entry.',
                'student_ref' => 'Leave blank for a blocked-out slot (not a lesson).',
                'date' => $date,
                'start_time' => 'HH:MM (24-hour).',
                'end_time' => 'HH:MM (24-hour), after start_time.',
                'status' => $list(ValidateImportBundleAction::DIARY_STATUSES).' Default: completed for past dates, booked otherwise. Future entries cannot be completed. Cancelled entries are skipped.',
                'price' => 'Pounds, e.g. 35.00.',
                'mileage' => 'Whole miles.',
            ],
            'finances' => [
                'finance_ref' => 'Your unique ID for the finance entry.',
                'type' => 'payment or expense.',
                'category' => 'For payments: '.implode(', ', array_keys((array) config('finances.payment_categories'))).'. For expenses: '.implode(', ', array_keys((array) config('finances.expense_categories'))).'.',
                'payment_method' => $list(array_keys((array) config('finances.payment_methods'))),
                'date' => $date,
                'amount' => 'Pounds, 0 or more, e.g. 35.00 (0.00 is allowed).',
                'description' => 'Up to 255 characters.',
                'receipt_file' => 'Path of a '.implode('/', (array) config('finances.receipt.allowed_mimes')).' file inside the zip, e.g. '.self::EXAMPLE_RECEIPT.'. Max '.((int) config('finances.receipt.max_size_kb') / 1024).'MB. File names must be unique.',
            ],
        ];
    }

    protected function readme(): string
    {
        $notes = $this->columnNotes();

        $lines = [
            '# Drive CRM data import',
            '',
            'Export your data as the CSV files below, zip them together (with any receipt files) and upload the zip on the Data Import page.',
            '',
            '- Every file is optional, but the zip must contain at least one of: '.implode(', ', ReadImportBundleAction::FILES).'.',
            '- The first row of each file is the header row. Column names are not case-sensitive and columns can be in any order. Unknown columns are ignored.',
            '- Refs (instructor_ref, student_ref, ...) are your own IDs from the old system. Rows already imported in an earlier run are skipped, so re-uploading is safe.',
            '- Nothing is imported unless every row is valid. No emails are sent by the import.',
            '- The example files in this zip link to each other and form a valid bundle. Replace the example rows with your data.',
        ];

        foreach (self::COLUMNS as $section => $columns) {
            $lines[] = '';
            $lines[] = '## '.ReadImportBundleAction::FILES[$section];
            $lines[] = '';
            $lines[] = '| Column | Required | Notes |';
            $lines[] = '| --- | --- | --- |';

            foreach ($columns as $column => $required) {
                $lines[] = "| {$column} | ".($required ? 'Yes' : 'No').' | '.($notes[$section][$column] ?? '').' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * A minimal one-page PDF so the example receipt_file resolves.
     */
    protected function examplePdf(): string
    {
        $text = 'BT /F1 18 Tf 72 720 Td (Example receipt - F-5002 - Fuel - 45.20) Tj ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%EOF\n";

        return $pdf;
    }
}
