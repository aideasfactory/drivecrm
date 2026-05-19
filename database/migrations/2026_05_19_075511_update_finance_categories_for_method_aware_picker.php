<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        $newRows = [
            // Vehicle running costs added for method-aware picker (Actual-method vehicles)
            ['category' => 'servicing', 'vat_treatment' => 'standard', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'repairs', 'vat_treatment' => 'standard', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'road_tax', 'vat_treatment' => 'outside_scope', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'breakdown_cover', 'vat_treatment' => 'standard', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'vehicle_insurance', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],

            // General categories added (not method-dependent)
            ['category' => 'business_insurance', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'otherExpenses', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'phone', 'vat_treatment' => 'standard', 'itsa_bucket' => 'adminCosts', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'accountant_fees', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'professionalFees', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
        ];

        foreach ($newRows as $row) {
            DB::table('category_tax_mapping')->updateOrInsert(
                ['category' => $row['category']],
                $row + ['created_at' => $now, 'updated_at' => $now],
            );
        }

        // food_drink stays in the table for historical-row integrity but is hidden from
        // the picker going forward (per locked decision §8 in the task spec).
        DB::table('category_tax_mapping')
            ->where('category', 'food_drink')
            ->update([
                'selectable_in_picker' => false,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('category_tax_mapping')
            ->whereIn('category', [
                'servicing',
                'repairs',
                'road_tax',
                'breakdown_cover',
                'vehicle_insurance',
                'business_insurance',
                'phone',
                'accountant_fees',
            ])
            ->delete();

        DB::table('category_tax_mapping')
            ->where('category', 'food_drink')
            ->update([
                'selectable_in_picker' => true,
                'updated_at' => Carbon::now(),
            ]);
    }
};
