<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('category_tax_mapping')->updateOrInsert(
            ['category' => 'franchise_fee'],
            [
                'vat_treatment' => 'exempt',
                'itsa_bucket' => 'professionalFees',
                'claimable' => true,
                'method_dependent' => false,
                'selectable_in_picker' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        // Existing type=payment rows keep `franchise_payout` so totals and past HMRC figures don't shift.
        DB::table('category_tax_mapping')
            ->where('category', 'franchise_payout')
            ->update([
                'selectable_in_picker' => false,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('category_tax_mapping')->where('category', 'franchise_fee')->delete();

        DB::table('category_tax_mapping')
            ->where('category', 'franchise_payout')
            ->update([
                'selectable_in_picker' => true,
                'updated_at' => Carbon::now(),
            ]);
    }
};
