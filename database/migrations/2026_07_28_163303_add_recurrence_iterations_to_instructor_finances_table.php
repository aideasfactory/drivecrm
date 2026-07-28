<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->unsignedSmallInteger('recurrence_iterations')->nullable()->after('recurrence_frequency');
            $table->uuid('recurrence_group_id')->nullable()->after('recurrence_iterations');

            $table->index('recurrence_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group_id']);
            $table->dropColumn(['recurrence_iterations', 'recurrence_group_id']);
        });
    }
};
