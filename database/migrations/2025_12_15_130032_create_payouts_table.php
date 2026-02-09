<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('instructor_id')->constrained()->onDelete('cascade');
            $table->integer('amount_pence');
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('stripe_transfer_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
