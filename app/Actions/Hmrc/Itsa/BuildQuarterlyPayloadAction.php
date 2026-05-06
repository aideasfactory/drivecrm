<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Enums\ItsaExpenseCategory;
use App\Support\HmrcMoney;

class BuildQuarterlyPayloadAction
{
    /**
     * Translate validated form data (pence integers) into HMRC's quarterly
     * update JSON payload (decimal pounds, camelCase keys).
     *
     * @param  array{
     *     period_start_date: string,
     *     period_end_date: string,
     *     turnover_pence: int,
     *     other_income_pence: int,
     *     consolidated_expenses_pence?: ?int,
     *     expenses?: array<string, ?int>,
     * }  $data
     * @return array<string, mixed>
     */
    public function __invoke(array $data): array
    {
        $payload = [
            'periodDates' => [
                'periodStartDate' => $data['period_start_date'],
                'periodEndDate' => $data['period_end_date'],
            ],
            'periodIncome' => [
                'turnover' => HmrcMoney::toHmrcPayload((int) $data['turnover_pence']),
                'other' => HmrcMoney::toHmrcPayload((int) $data['other_income_pence']),
            ],
        ];

        $consolidated = $data['consolidated_expenses_pence'] ?? null;

        if ($consolidated !== null) {
            $payload['periodExpenses'] = [
                'consolidatedExpenses' => HmrcMoney::toHmrcPayload((int) $consolidated),
            ];

            return $payload;
        }

        $itemised = [];
        $expenseInputs = is_array($data['expenses'] ?? null) ? $data['expenses'] : [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $value = $expenseInputs[$category->value] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $itemised[$category->hmrcKey()] = HmrcMoney::toHmrcPayload((int) $value);
        }

        if ($itemised !== []) {
            $payload['periodExpenses'] = $itemised;
        }

        return $payload;
    }
}
