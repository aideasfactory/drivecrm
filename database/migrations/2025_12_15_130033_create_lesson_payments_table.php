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
        if (! Schema::hasTable('lesson_payments')) {
            Schema::create('lesson_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
                $table->integer('amount_pence');
                $table->enum('status', ['due', 'paid', 'refunded'])->default('due');
                $table->date('due_date')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->string('stripe_invoice_id')->nullable();
                $table->timestamps();

                $table->index(['lesson_id', 'status']);
            });

            return;
        }

        if (! Schema::hasColumn('lesson_payments', 'lesson_id')) {
            return;
        }

        try {
            Schema::table('lesson_payments', function (Blueprint $table) {
                $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            });
        } catch (Throwable) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_payments');
    }
};
