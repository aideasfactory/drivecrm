<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\InstructorFinance;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateInstructorFinanceAction
{
    /**
     * Create a finance record. When recurring, materialise the full series
     * upfront: `recurrence_iterations` records in total (including the first),
     * dates stepped by the frequency, linked via a shared recurrence_group_id.
     *
     * @param  array<string, mixed>  $data
     * @return InstructorFinance The first record of the series
     */
    public function __invoke(Instructor $instructor, array $data): InstructorFinance
    {
        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $iterations = $isRecurring ? max(1, (int) ($data['recurrence_iterations'] ?? 1)) : 1;
        $frequency = $isRecurring ? ($data['recurrence_frequency'] ?? null) : null;
        $groupId = ($isRecurring && $iterations > 1) ? Str::uuid()->toString() : null;

        $attributes = [
            'type' => $data['type'],
            'category' => $data['category'] ?? 'none',
            'lesson_payment_id' => $data['lesson_payment_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'description' => $data['description'],
            'amount_pence' => $data['amount_pence'],
            'is_recurring' => $isRecurring,
            'recurrence_frequency' => $frequency,
            'recurrence_iterations' => $isRecurring ? $iterations : null,
            'recurrence_group_id' => $groupId,
            'notes' => $data['notes'] ?? null,
        ];

        $date = Carbon::parse($data['date']);
        $first = null;

        for ($occurrence = 0; $occurrence < $iterations; $occurrence++) {
            $finance = $instructor->finances()->create($attributes + [
                'date' => $this->occurrenceDate($date, $frequency, $occurrence)->format('Y-m-d'),
            ]);

            $first ??= $finance;
        }

        return $first;
    }

    /**
     * Date of the Nth occurrence, stepped without month/year overflow
     * (e.g. 31 Jan + 1 month = 28 Feb, not 3 Mar).
     */
    private function occurrenceDate(Carbon $start, ?string $frequency, int $occurrence): Carbon
    {
        if ($occurrence === 0 || $frequency === null) {
            return $start;
        }

        return match ($frequency) {
            'weekly' => $start->copy()->addWeeks($occurrence),
            'monthly' => $start->copy()->addMonthsNoOverflow($occurrence),
            'yearly' => $start->copy()->addYearsNoOverflow($occurrence),
            default => $start,
        };
    }
}
