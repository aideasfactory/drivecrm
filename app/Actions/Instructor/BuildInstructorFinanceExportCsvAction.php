<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;
use App\Models\MileageLog;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Builds the instructor's finance export CSV from the same data the Finances
 * overview screen shows: manual payments + lesson income, expenses, then
 * mileage, each oldest first.
 */
class BuildInstructorFinanceExportCsvAction
{
    public const COLUMNS = [
        'record_type',
        'date',
        'source',
        'category',
        'description',
        'amount_gbp',
        'payment_method',
        'mileage_type',
        'start_mileage',
        'end_mileage',
        'miles',
        'notes',
    ];

    /**
     * @param  Collection<int, InstructorFinance>  $finances
     * @param  Collection<int, array{source: string, description: string, package_name: string|null, date: string|null, amount_pence: int}>  $lessonIncome
     * @param  Collection<int, MileageLog>  $mileage
     */
    public function __invoke(Collection $finances, Collection $lessonIncome, Collection $mileage): string
    {
        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new RuntimeException('Could not open a buffer for the finance export.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS, escape: '');

        $paymentRows = $finances->where('type', 'payment')
            ->map(fn (InstructorFinance $finance): array => $this->financeRow($finance))
            ->concat($lessonIncome->map(fn (array $income): array => [
                'payment',
                $income['date'],
                $income['source'],
                'Lesson income',
                $income['description'],
                $this->gbp($income['amount_pence']),
                null,
                null,
                null,
                null,
                null,
                $income['package_name'],
            ]))
            ->sortBy(fn (array $row): string => (string) $row[1]);

        $expenseRows = $finances->where('type', 'expense')
            ->sortBy(fn (InstructorFinance $finance): string => $finance->date->toDateString())
            ->map(fn (InstructorFinance $finance): array => $this->financeRow($finance));

        $mileageRows = $mileage
            ->sortBy(fn (MileageLog $log): string => $log->date->toDateString())
            ->map(fn (MileageLog $log): array => [
                'mileage',
                $log->date->toDateString(),
                null,
                null,
                null,
                null,
                null,
                config("finances.mileage_types.{$log->type}", $log->type),
                $log->start_mileage,
                $log->end_mileage,
                $log->miles,
                $log->notes,
            ]);

        foreach ([$paymentRows, $expenseRows, $mileageRows] as $rows) {
            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->neutraliseFormula(...), $row), escape: '');
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return list<string|int|null>
     */
    private function financeRow(InstructorFinance $finance): array
    {
        $categories = $finance->type === 'payment' ? 'payment_categories' : 'expense_categories';

        return [
            $finance->type,
            $finance->date->toDateString(),
            'manual',
            config("finances.{$categories}.{$finance->category}", $finance->category),
            $finance->description,
            $this->gbp($finance->amount_pence),
            $finance->payment_method ? config("finances.payment_methods.{$finance->payment_method}", $finance->payment_method) : null,
            null,
            null,
            null,
            null,
            $finance->notes,
        ];
    }

    /**
     * Prefix free-text cells that a spreadsheet would run as a formula
     * (= + - @, tab, CR) with an apostrophe. Numbers are left untouched.
     */
    private function neutraliseFormula(string|int|float|null $value): string|int|float|null
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private function gbp(int $pence): string
    {
        return number_format($pence / 100, 2, '.', '');
    }
}
