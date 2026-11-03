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
            $table->string('receipt_status', 20)->nullable()->after('receipt_size_bytes');
            $table->string('receipt_thumbnail_path')->nullable()->after('receipt_status');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropColumn(['receipt_status', 'receipt_thumbnail_path']);
        });
    }
};
