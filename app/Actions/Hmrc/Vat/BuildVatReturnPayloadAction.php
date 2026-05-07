<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Vat;

use App\Support\HmrcMoney;

class BuildVatReturnPayloadAction
{
    /**
     * Translate the validated 9-box pence figures into the HMRC submit payload.
     *
     * Boxes 1–5 are decimal pounds (2dp). Boxes 6–9 are whole pounds at HMRC.
     * `finalised` is always `true` in v1 — HMRC's binding "this is the final return" flag.
     *
     * @param  array{
     *     period_key: string,
     *     vat_due_sales_pence: int,
     *     vat_due_acquisitions_pence: int,
     *     total_vat_due_pence: int,
     *     vat_reclaimed_curr_period_pence: int,
     *     net_vat_due_pence: int,
     *     total_value_sales_ex_vat_pence: int,
     *     total_value_purchases_ex_vat_pence: int,
     *     total_value_goods_supplied_ex_vat_pence: int,
     *     total_acquisitions_ex_vat_pence: int,
     * }  $data
     * @return array<string, mixed>
     */
    public function __invoke(array $data): array
    {
        return [
            'periodKey' => $data['period_key'],
            'vatDueSales' => HmrcMoney::toHmrcPayload($data['vat_due_sales_pence'], allowNegative: false),
            'vatDueAcquisitions' => HmrcMoney::toHmrcPayload($data['vat_due_acquisitions_pence'], allowNegative: false),
            'totalVatDue' => HmrcMoney::toHmrcPayload($data['total_vat_due_pence'], allowNegative: false),
            'vatReclaimedCurrPeriod' => HmrcMoney::toHmrcPayload($data['vat_reclaimed_curr_period_pence'], allowNegative: false),
            'netVatDue' => HmrcMoney::toHmrcPayload($data['net_vat_due_pence'], allowNegative: false),
            'totalValueSalesExVAT' => $this->wholePounds($data['total_value_sales_ex_vat_pence']),
            'totalValuePurchasesExVAT' => $this->wholePounds($data['total_value_purchases_ex_vat_pence']),
            'totalValueGoodsSuppliedExVAT' => $this->wholePounds($data['total_value_goods_supplied_ex_vat_pence']),
            'totalAcquisitionsExVAT' => $this->wholePounds($data['total_acquisitions_ex_vat_pence']),
            'finalised' => true,
        ];
    }

    /**
     * HMRC requires whole pounds (integer) for boxes 6–9. We store pence in DB
     * but submit pounds; round to nearest pound.
     */
    private function wholePounds(int $pence): int
    {
        return (int) round($pence / 100);
    }
}
