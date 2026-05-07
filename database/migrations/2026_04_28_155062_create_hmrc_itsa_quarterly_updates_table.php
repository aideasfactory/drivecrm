<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_quarterly_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_id', 64)->index();
            $table->string('period_key', 64)->index();
            $table->date('period_start_date');
            $table->date('period_end_date');

            // Income (pence)
            $table->bigInteger('turnover_pence')->default(0);
            $table->bigInteger('other_income_pence')->default(0);

            // Consolidated expenses option (mutually exclusive with itemised below)
            $table->bigInteger('consolidated_expenses_pence')->nullable();

            // Itemised expenses (all pence, all nullable; presence implies itemised mode)
            $table->bigInteger('cost_of_goods_pence')->nullable();
            $table->bigInteger('payments_to_subcontractors_pence')->nullable();
            $table->bigInteger('wages_and_staff_costs_pence')->nullable();
            $table->bigInteger('car_van_travel_expenses_pence')->nullable();
            $table->bigInteger('premises_running_costs_pence')->nullable();
            $table->bigInteger('maintenance_costs_pence')->nullable();
            $table->bigInteger('admin_costs_pence')->nullable();
            $table->bigInteger('business_entertainment_costs_pence')->nullable();
            $table->bigInteger('advertising_costs_pence')->nullable();
            $table->bigInteger('interest_on_bank_other_loans_pence')->nullable();
            $table->bigInteger('finance_charges_pence')->nullable();
            $table->bigInteger('irrecoverable_debts_pence')->nullable();
            $table->bigInteger('professional_fees_pence')->nullable();
            $table->bigInteger('depreciation_pence')->nullable();
            $table->bigInteger('other_expenses_pence')->nullable();

            // Audit trail (current state — revisions table is the immutable history)
            $table->string('submission_id', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('digital_records_attested_at')->nullable();
            $table->foreignId('digital_records_attested_by_user_id')->nullable();
            $table->foreign('digital_records_attested_by_user_id', 'hmrc_itsa_qu_attested_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'business_id', 'period_key'], 'hmrc_itsa_qu_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_quarterly_updates');
    }
};
