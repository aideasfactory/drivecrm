<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_vat_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vrn', 9)->index();
            $table->string('period_key', 16)->index();

            // 9-box totals (pence). VAT amount boxes (1–5) — non-negative.
            $table->bigInteger('vat_due_sales_pence');
            $table->bigInteger('vat_due_acquisitions_pence');
            $table->bigInteger('total_vat_due_pence');
            $table->bigInteger('vat_reclaimed_curr_period_pence');
            $table->bigInteger('net_vat_due_pence');

            // Value boxes (6–9) — whole pounds at HMRC, stored as pence here for consistency.
            $table->bigInteger('total_value_sales_ex_vat_pence');
            $table->bigInteger('total_value_purchases_ex_vat_pence');
            $table->bigInteger('total_value_goods_supplied_ex_vat_pence');
            $table->bigInteger('total_acquisitions_ex_vat_pence');

            $table->boolean('finalised')->default(true);

            // HMRC response payload
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processing_date')->nullable();
            $table->string('form_bundle_number', 32)->nullable();
            $table->string('charge_ref_number', 32)->nullable();
            $table->string('payment_indicator', 8)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->timestamp('digital_records_attested_at')->nullable();
            $table->foreignId('digital_records_attested_by_user_id')->nullable();
            $table->foreign('digital_records_attested_by_user_id', 'hmrc_vat_ret_attested_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'vrn', 'period_key'], 'hmrc_vat_ret_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_vat_returns');
    }
};
