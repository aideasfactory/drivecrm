<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Models\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WriteFinancesCsvAction
{
    /**
     * Write `finances.csv` inside the staging directory. Includes every payment
     * and expense row dated within the tax year, including the rows excluded
     * from HMRC payloads (Simplified vehicle running costs, food_drink, etc.) —
     * because the accountant may still want them for end-of-year reconciliation
     * and the instructor's own books.
     *
     * Returns the number of rows written (excluding the header).
     */
    public function __invoke(string $stagingDir, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        $path = $stagingDir.'/finances.csv';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Could not open finances.csv for writing.');
        }

        fputcsv($handle, [
            'date',
            'type',
            'category',
            'category_label',
            'description',
            'amount_pence',
            'amount_gbp',
            'payment_method',
            'vehicle_id',
            'vehicle_display_name',
            'vehicle_method',
            'itsa_bucket',
            'method_dependent',
            'claimable',
            'is_recurring',
            'recurrence_frequency',
            'notes',
            'receipt_filename',
            'created_at',
        ]);

        $rows = DB::table('instructor_finances')
            ->leftJoin('category_tax_mapping', 'category_tax_mapping.category', '=', 'instructor_finances.category')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'instructor_finances.vehicle_id')
            ->where('instructor_finances.instructor_id', $instructor->id)
            ->whereBetween('instructor_finances.date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('instructor_finances.date')
            ->select(
                'instructor_finances.*',
                'category_tax_mapping.itsa_bucket as itsa_bucket',
                'category_tax_mapping.method_dependent as method_dependent',
                'category_tax_mapping.claimable as claimable',
                'vehicles.display_name as vehicle_display_name',
                'vehicles.method as vehicle_method',
            )
            ->cursor();

        $count = 0;
        foreach ($rows as $row) {
            $expenseLabel = config("finances.expense_categories.{$row->category}");
            $paymentLabel = config("finances.payment_categories.{$row->category}");

            fputcsv($handle, [
                $row->date,
                $row->type,
                $row->category,
                $row->type === 'payment' ? $paymentLabel : $expenseLabel,
                $row->description,
                $row->amount_pence,
                number_format($row->amount_pence / 100, 2, '.', ''),
                $row->payment_method,
                $row->vehicle_id,
                $row->vehicle_display_name,
                $row->vehicle_method,
                $row->itsa_bucket,
                $row->method_dependent ? 'true' : 'false',
                $row->claimable ? 'true' : 'false',
                $row->is_recurring ? 'true' : 'false',
                $row->recurrence_frequency,
                $row->notes,
                $row->receipt_original_name,
                $row->created_at,
            ]);
            $count++;
        }

        fclose($handle);

        return $count;
    }
}
