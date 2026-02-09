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
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('instructor_id')->nullable()->constrained('instructors')->onDelete('cascade');
                $table->foreignId('package_id')->constrained()->onDelete('cascade');
                $table->enum('payment_mode', ['upfront', 'weekly'])->default('upfront');
                $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
                $table->string('stripe_payment_intent_id')->nullable();
                $table->string('stripe_subscription_id')->nullable();
                $table->string('stripe_checkout_session_id')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'status']);
                $table->index(['instructor_id', 'status']);
            });

            return;
        }

        if (! Schema::hasColumn('orders', 'package_id')) {
            return;
        }

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');
            });
        } catch (Throwable) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
