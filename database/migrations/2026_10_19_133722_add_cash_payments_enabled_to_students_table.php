<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash payments are switched on per pupil by a full-access owner. Off for
     * everyone by default, including pupils brought in by the importer. Must
     * run before the code that reads it is deployed.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('cash_payments_enabled')->default(false)->after('owns_account');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('cash_payments_enabled');
        });
    }
};
