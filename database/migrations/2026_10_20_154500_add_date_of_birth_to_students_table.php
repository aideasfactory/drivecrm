<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff edit a pupil's date of birth from the admin pupil profile.
     * Nullable: existing pupils have no stored value, and the profile falls
     * back to the date of birth captured on their booking enquiry. Addresses
     * are not stored here; they live in student_pickup_points.
     * Must run before the code that reads it is deployed.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
