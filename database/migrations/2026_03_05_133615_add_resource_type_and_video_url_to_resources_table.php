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
        Schema::table('resources', function (Blueprint $table) {
            $table->string('resource_type', 20)->default('file')->after('tags');
            $table->string('video_url', 500)->nullable()->after('resource_type');

            // Make file columns nullable for video_link resources
            $table->string('file_path', 500)->nullable()->change();
            $table->string('file_name')->nullable()->change();
            $table->unsignedBigInteger('file_size')->nullable()->change();
            $table->string('mime_type', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['resource_type', 'video_url']);

            // Restore file columns to non-nullable
            $table->string('file_path', 500)->nullable(false)->change();
            $table->string('file_name')->nullable(false)->change();
            $table->unsignedBigInteger('file_size')->nullable(false)->change();
            $table->string('mime_type', 100)->nullable(false)->change();
        });
    }
};
