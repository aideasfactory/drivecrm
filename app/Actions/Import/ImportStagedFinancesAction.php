<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\InstructorFinance;
use App\Support\ImportValues;

class ImportStagedFinancesAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected FindImportedInstructorAction $findImportedInstructor,
        protected ImportFinanceAction $importFinance,
    ) {}

    /**
     * Stage 7 for one instructor ref: their finance rows (instructor
     * bookkeeping — not lesson payments, payouts or Stripe).
     *
     * - New ref → added with the pupil and lesson it was for (when those are
     *   in Drive). Refunded/outstanding status and the registration go in the
     *   notes; a registration that isn't one (e.g. "OWNCAR") is left out.
     * - Imported before → only the new pupil/lesson links are filled, where
     *   empty. Nothing else on the row changes.
     * - Bad type, date or amount → skipped.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['finances_added' => 0, 'already_imported' => 0, 'links_filled' => 0, 'skipped' => 0];
        $instructor = ($this->findImportedInstructor)($instructorRef);

        if ($instructor === null) {
            return $counts;
        }

        $rows = array_column($this->readStagedRows->all($run, 'finances', $instructorRef), 'row');
        $mapped = ImportMapping::modelIdsFor(ImportMapping::ENTITY_FINANCE, array_column($rows, 'finance_ref'));
        $studentIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_STUDENT, array_filter(array_column($rows, 'student_ref')));
        $lessonIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_LESSON, array_filter(array_column($rows, 'diary_ref')));

        foreach ($rows as $row) {
            $links = array_filter([
                'student_id' => $studentIds[$row['student_ref'] ?? ''] ?? null,
                'lesson_id' => $lessonIds[$row['diary_ref'] ?? ''] ?? null,
            ]);

            if (isset($mapped[$row['finance_ref']])) {
                $counts['already_imported']++;
                $counts['links_filled'] += $this->fillLinks($mapped[$row['finance_ref']], $links + $this->paymentMethod($row));

                continue;
            }

            $type = (string) $row['type'];

            if (! in_array($type, ['payment', 'expense'], true) || ImportValues::date((string) $row['date']) === null || ImportValues::pence((string) $row['amount']) === null) {
                $counts['skipped']++;

                continue;
            }

            $finance = ($this->importFinance)($instructor, $row, $run, null, [], $links + ['notes' => $this->notes($row)]);
            $counts['finances_added'] += (int) ($finance !== null);
        }

        return $counts;
    }

    /**
     * The row's payment method as a fill (`payment_method` => value), only when Drive knows it.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    protected function paymentMethod(array $row): array
    {
        $method = trim((string) ($row['payment_method'] ?? ''));

        return $method !== '' && array_key_exists($method, (array) config('finances.payment_methods'))
            ? ['payment_method' => $method]
            : [];
    }

    /**
     * Fill an imported finance row's pupil/lesson links and payment method
     * where they're empty. Nothing already set is changed.
     *
     * @param  array<string, int|string>  $links
     * @return int Fields filled
     */
    protected function fillLinks(int $financeId, array $links): int
    {
        $filled = 0;

        foreach ($links as $column => $id) {
            $filled += InstructorFinance::query()->whereKey($financeId)->whereNull($column)->update([$column => $id]);
        }

        return $filled;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function notes(array $row): ?string
    {
        $status = strtolower(trim((string) ($row['payment_status'] ?? '')));
        $registration = trim((string) ($row['vehicle_reg'] ?? ''));

        $notes = array_filter([
            trim((string) ($row['notes'] ?? '')),
            in_array($status, ['refunded', 'outstanding'], true) ? 'Payment status: '.ucfirst($status) : '',
            $registration !== '' && CheckStagedImportAction::looksLikeRegistration($registration) ? "Vehicle: {$registration}" : '',
        ]);

        return $notes !== [] ? implode("\n", $notes) : null;
    }
}
