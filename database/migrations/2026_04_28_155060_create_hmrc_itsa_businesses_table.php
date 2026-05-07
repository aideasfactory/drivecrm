<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_itsa_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_id', 64)->index();
            $table->string('type_of_business', 32);
            $table->string('trading_name', 160)->nullable();
            $table->string('accounting_type', 16)->nullable();
            $table->date('commencement_date')->nullable();
            $table->date('cessation_date')->nullable();
            $table->json('latency_details')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_itsa_businesses');
    }
};
