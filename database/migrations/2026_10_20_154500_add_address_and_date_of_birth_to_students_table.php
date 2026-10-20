<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff edit a pupil's home address and date of birth from the admin pupil
     * profile. All nullable: existing pupils have no stored value, and the
     * profile falls back to the address captured on their booking enquiry.
     * Must run before the code that reads it is deployed.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city', 100)->nullable()->after('address_line_2');
            $table->string('postcode', 10)->nullable()->after('city');
            $table->date('date_of_birth')->nullable()->after('postcode');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['address_line_1', 'address_line_2', 'city', 'postcode', 'date_of_birth']);
        });
    }
};
