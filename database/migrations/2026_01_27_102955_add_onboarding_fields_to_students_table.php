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
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable();
            $table->string('surname', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('contact_first_name', 255)->nullable();
            $table->string('contact_surname', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->boolean('allow_communications')->default(false);
            $table->boolean('contact_terms')->nullable();
            $table->boolean('contact_communications')->nullable();
            $table->boolean('owns_account')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'surname',
                'email',
                'phone',
                'contact_first_name',
                'contact_surname',
                'contact_email',
                'contact_phone',
                'terms_accepted',
                'allow_communications',
                'contact_terms',
                'contact_communications',
                'owns_account',
            ]);
        });
    }
};
