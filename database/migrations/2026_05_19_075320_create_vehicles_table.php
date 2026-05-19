<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 100);
            $table->string('registration', 16)->nullable();
            $table->unsignedSmallInteger('engine_size_cc')->nullable();
            $table->string('method', 16)->default('simplified');
            $table->decimal('business_use_percentage', 5, 2)->default(95.00);
            $table->date('acquired_on');
            $table->date('disposed_on')->nullable();
            $table->timestamp('lifetime_method_locked_at')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'disposed_on']);
            $table->index(['instructor_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
