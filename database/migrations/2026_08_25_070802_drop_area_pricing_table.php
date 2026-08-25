<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Area-based (postcode/transmission) pricing replaced by a per-instructor
     * price uplift — see instructors.price_uplift_pence.
     */
    public function up(): void
    {
        Schema::dropIfExists('area_pricing');
    }

    public function down(): void
    {
        Schema::create('area_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('outcode', 10)->unique();
            $table->integer('all_premium_pence')->default(0);
            $table->integer('manual_premium_pence')->default(0);
            $table->integer('automatic_premium_pence')->default(0);
            $table->timestamps();
        });
    }
};
