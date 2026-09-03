<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_item_id')->unique()->constrained('calendar_items')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('instructors')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_offers');
    }
};
