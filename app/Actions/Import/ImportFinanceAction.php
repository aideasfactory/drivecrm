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
     * A category or payment method Drive doesn't know becomes `imported` /
     * blank. A receipt_file with no matching file in the zip is left for
     * `import:receipts`.
     *
     * @param  array<string, string|int>  $row
     * @param  array<string, array{name: string, size: int}|null>  $attachments  From ReadImportBundleAction
     * @param  array<string, mixed>  $extra  Extra columns to set (e.g. student_id, lesson_id, notes)
     */
    public function __invoke(Instructor $instructor, array $row, ImportRun $run, ?ZipArchive $zip, array $attachments, array $extra = []): ?InstructorFinance
    {
        $ref = (string) $row['finance_ref'];

        if (ImportMapping::modelIdFor(ImportMapping::ENTITY_FINANCE, $ref) !== null) {
            return null;
        }

        $type = (string) $row['type'];
        $category = trim((string) ($row['category'] ?? ''));
        $paymentMethod = trim((string) ($row['payment_method'] ?? ''));

        if (! array_key_exists($category, (array) config("finances.{$type}_categories"))) {
            $category = '';
        }

        if (! array_key_exists($paymentMethod, (array) config('finances.payment_methods'))) {
            $paymentMethod = '';
        }

        $notes = trim((string) ($row['notes'] ?? ''));

        $finance = InstructorFinance::create(array_merge([
            'instructor_id' => $instructor->id,
            'type' => $type,
            'category' => $category !== '' ? $category : self::DEFAULT_CATEGORY,
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
            'description' => mb_substr((string) $row['description'], 0, 255),
            'amount_pence' => (int) ImportValues::pence((string) $row['amount']),
            'is_recurring' => false,
            'date' => ImportValues::date((string) $row['date'])->toDateString(),
            'notes' => $notes !== '' ? $notes : null,
        ], $extra));

        $receiptFile = trim((string) ($row['receipt_file'] ?? ''));

        $attachment = $receiptFile !== '' ? ($attachments[strtolower(basename(str_replace('\\', '/', $receiptFile)))] ?? null) : null;

        if ($zip !== null && $attachment !== null) {
            ($this->attachImportedReceipt)($finance, $zip, $attachment['name']);
        }

        ImportMapping::record($run, ImportMapping::ENTITY_FINANCE, $ref, $finance->id);

        return $finance;
    }
}
