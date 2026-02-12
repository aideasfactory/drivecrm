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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');

            // Contact details
            $table->string('name');
            $table->string('relationship', 100);
            $table->string('phone', 50);
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // Indexes for performance
            $table->index(['contactable_type', 'contactable_id'], 'contacts_contactable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
