<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16);
            $table->string('file_name')->nullable();
            $table->string('status', 16)->default('running');
            $table->json('totals')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('import_mappings', function (Blueprint $table) {
            $table->foreignId('import_run_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('action', 16)->default('created')->after('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_run_id');
            $table->dropColumn('action');
        });

        Schema::dropIfExists('import_runs');
    }
};
