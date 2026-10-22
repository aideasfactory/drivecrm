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
            $table->string('source', 32)->default('manual')->after('type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source');
            $table->boolean('linked_from_manual')->default(false)->after('source_id');

            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id', 'linked_from_manual']);
        });
    }
};
