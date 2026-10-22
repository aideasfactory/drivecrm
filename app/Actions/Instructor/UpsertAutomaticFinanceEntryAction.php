<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\FinanceSource;
use App\Models\InstructorFinance;
use Illuminate\Database\UniqueConstraintViolationException;

class UpsertAutomaticFinanceEntryAction
{
    /**
     * Write (or remove) the single automatic finance row for a source record.
     * Idempotent: the unique (source, source_id) index guarantees one row per
     * payout / lesson, however many times this runs.
     *
     * When no automatic row exists yet, an unlinked manual payment the
     * instructor already entered by hand for the same amount on a matching
     * date is linked instead of adding a second row, so the income is never
     * counted twice. Linked rows keep their notes and receipt, and go back to
     * being manual if the source stops qualifying.
     *
     * @param  array<string, mixed>|null  $attributes  Finance columns to write; null removes the automatic row.
     * @param  array<int, string>  $matchDates  Y-m-d dates a hand-entered duplicate may be dated on.
     * @param  array<int, string|null>  $matchPaymentMethods  Payment methods a hand-entered duplicate may use.
     */
    public function __invoke(
        int $instructorId,
        FinanceSource $source,
        int $sourceId,
        ?array $attributes,
        array $matchDates = [],
        array $matchPaymentMethods = [],
    ): ?InstructorFinance {
        $existing = InstructorFinance::query()
            ->where('source', $source->value)
            ->where('source_id', $sourceId)
            ->first();

        if ($attributes === null) {
            if ($existing) {
                $this->release($existing);
            }

            return null;
        }

        $attributes = [
            ...$attributes,
            'instructor_id' => $instructorId,
            'type' => 'payment',
            'vehicle_id' => null,
            'is_recurring' => false,
            'recurrence_frequency' => null,
            'recurrence_iterations' => null,
            'recurrence_group_id' => null,
        ];

        if ($existing) {
            if ($existing->linked_from_manual) {
                unset($attributes['notes']);
            }

            $existing->fill($attributes);

            if ($existing->isDirty()) {
                $existing->save();
            }

            return $existing;
        }

        $manualMatch = $this->findHandEnteredDuplicate($instructorId, (int) $attributes['amount_pence'], $matchDates, $matchPaymentMethods);

        if ($manualMatch) {
            $originalDescription = $manualMatch->description;
            $notes = trim(implode("\n", array_filter([
                $manualMatch->notes,
                "Entered by hand as: {$originalDescription}",
            ])));

            $manualMatch->fill([
                ...$attributes,
                'source' => $source,
                'source_id' => $sourceId,
                'linked_from_manual' => true,
                'payment_method' => $manualMatch->payment_method ?? $attributes['payment_method'] ?? null,
                'notes' => mb_substr($notes, 0, 1000),
            ])->save();

            return $manualMatch;
        }

        try {
            return InstructorFinance::query()->create([
                ...$attributes,
                'source' => $source,
                'source_id' => $sourceId,
                'linked_from_manual' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return InstructorFinance::query()
                ->where('source', $source->value)
                ->where('source_id', $sourceId)
                ->first();
        }
    }

    /**
     * The source no longer counts as income (payout no longer paid, lesson
     * no longer signed off, source deleted). Rows the system created are
     * removed; rows linked from a manual entry go back to being manual.
     */
    private function release(InstructorFinance $finance): void
    {
        if (! $finance->linked_from_manual) {
            $finance->delete();

            return;
        }

        $finance->fill([
            'source' => FinanceSource::Manual,
            'source_id' => null,
            'linked_from_manual' => false,
        ])->save();
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<int, string|null>  $paymentMethods
     */
    private function findHandEnteredDuplicate(int $instructorId, int $amountPence, array $dates, array $paymentMethods): ?InstructorFinance
    {
        $dates = array_values(array_unique(array_filter($dates)));

        if ($dates === [] || $amountPence <= 0) {
            return null;
        }

        $allowsNullMethod = in_array(null, $paymentMethods, true);
        $methods = array_values(array_filter($paymentMethods, fn ($method) => $method !== null));

        return InstructorFinance::query()
            ->manual()
            ->where('instructor_id', $instructorId)
            ->where('type', 'payment')
            ->where('amount_pence', $amountPence)
            ->where('is_recurring', false)
            ->where(fn ($query) => $query->whereNull('category')->orWhere('category', 'none'))
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('date', $date);
                }
            })
            ->when($paymentMethods !== [], fn ($query) => $query->where(function ($query) use ($methods, $allowsNullMethod) {
                if ($methods !== []) {
                    $query->whereIn('payment_method', $methods);
                }

                if ($allowsNullMethod) {
                    $query->orWhereNull('payment_method');
                }
            }))
            ->orderBy('id')
            ->first();
    }
}
