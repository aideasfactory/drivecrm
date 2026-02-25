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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_folder_id')->constrained('resource_folders')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('thumbnail_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('resource_folder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
