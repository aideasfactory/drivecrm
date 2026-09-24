<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\InstructorFinance;
use App\Support\ImportValues;
use ZipArchive;

class ImportFinanceAction
{
    public const DEFAULT_CATEGORY = 'imported';

    public function __construct(
        protected AttachImportedReceiptAction $attachImportedReceipt,
    ) {}

    /**
     * Record one finances.csv row as an instructor finance entry. Rows with no
     * category land in the `imported` catch-all (tax-treated like `none`).
     * A `receipt_file` is copied out of the zip and attached like a normal
     * upload. Returns null when the row was imported on an earlier run.
     *
     * @param  array<string, string|int>  $row
     * @param  array<string, array{name: string, size: int}|null>  $attachments  From ReadImportBundleAction
     */
    public function __invoke(Instructor $instructor, array $row, ImportRun $run, ZipArchive $zip, array $attachments): ?InstructorFinance
    {
        $ref = (string) $row['finance_ref'];

        if (ImportMapping::modelIdFor(ImportMapping::ENTITY_FINANCE, $ref) !== null) {
            return null;
        }

        $category = trim((string) ($row['category'] ?? ''));
        $paymentMethod = trim((string) ($row['payment_method'] ?? ''));
        $notes = trim((string) ($row['notes'] ?? ''));

        $finance = InstructorFinance::create([
            'instructor_id' => $instructor->id,
            'type' => (string) $row['type'],
            'category' => $category !== '' ? $category : self::DEFAULT_CATEGORY,
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
            'description' => (string) $row['description'],
            'amount_pence' => (int) ImportValues::pence((string) $row['amount']),
            'is_recurring' => false,
            'date' => ImportValues::date((string) $row['date'])->toDateString(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $receiptFile = trim((string) ($row['receipt_file'] ?? ''));

        if ($receiptFile !== '') {
            $attachment = $attachments[strtolower(basename(str_replace('\\', '/', $receiptFile)))];
            ($this->attachImportedReceipt)($finance, $zip, $attachment['name']);
        }

        ImportMapping::record($run, ImportMapping::ENTITY_FINANCE, $ref, $finance->id);

        return $finance;
    }
}
