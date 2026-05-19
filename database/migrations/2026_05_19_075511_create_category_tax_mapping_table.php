<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_tax_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('category', 64)->unique();
            $table->string('vat_treatment', 32)->default('exempt');
            $table->string('itsa_bucket', 64)->nullable();
            $table->boolean('claimable')->default(true);
            $table->boolean('method_dependent')->default(false);
            $table->boolean('selectable_in_picker')->default(true);
            $table->timestamps();
        });

        $now = Carbon::now();

        $rows = [
            // Catch-all + internal
            ['category' => 'none', 'vat_treatment' => 'outside_scope', 'itsa_bucket' => null, 'claimable' => false, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'our_account', 'vat_treatment' => 'outside_scope', 'itsa_bucket' => null, 'claimable' => false, 'method_dependent' => false, 'selectable_in_picker' => true],

            // General expenses
            ['category' => 'advertising', 'vat_treatment' => 'standard', 'itsa_bucket' => 'advertisingCosts', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'association', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'professionalFees', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'bank_charges', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'financeCharges', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'computer_dvsa_fees', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'professionalFees', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'equipment', 'vat_treatment' => 'standard', 'itsa_bucket' => 'otherExpenses', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'food_drink', 'vat_treatment' => 'standard', 'itsa_bucket' => null, 'claimable' => false, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'internet', 'vat_treatment' => 'standard', 'itsa_bucket' => 'adminCosts', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],

            // Vehicle running costs (method-dependent — only claimable when vehicle.method = Actual)
            ['category' => 'fuel', 'vat_treatment' => 'standard', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'insurance', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],
            ['category' => 'mot', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'carVanTravelExpenses', 'claimable' => true, 'method_dependent' => true, 'selectable_in_picker' => true],

            // Payment categories (recorded in instructor_finances with type=payment but share the category column)
            ['category' => 'franchise_payout', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'professionalFees', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'hmrc_tax', 'vat_treatment' => 'outside_scope', 'itsa_bucket' => null, 'claimable' => false, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'referral', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'advertisingCosts', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
            ['category' => 'pupil_transfer_referral', 'vat_treatment' => 'exempt', 'itsa_bucket' => 'advertisingCosts', 'claimable' => true, 'method_dependent' => false, 'selectable_in_picker' => true],
        ];

        DB::table('category_tax_mapping')->insert(array_map(
            fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
            $rows,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('category_tax_mapping');
    }
};
