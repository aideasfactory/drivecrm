<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_tests', function (Blueprint $table): void {
            $table->string('mode', 20)->default('mock')->after('category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mock_tests', function (Blueprint $table): void {
            $table->dropIndex(['mode']);
            $table->dropColumn('mode');
        });
    }
};
