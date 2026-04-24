<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('type');
            $table->string('payment_method', 32)->nullable()->after('category');
            $table->string('receipt_path')->nullable()->after('notes');
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->string('receipt_mime_type', 64)->nullable()->after('receipt_original_name');
            $table->unsignedInteger('receipt_size_bytes')->nullable()->after('receipt_mime_type');

            $table->index(['instructor_id', 'category']);
        });

        DB::table('instructor_finances')
            ->whereNull('category')
            ->update(['category' => 'none']);

        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->string('category', 64)->nullable(false)->default('none')->change();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropIndex(['instructor_id', 'category']);
            $table->dropColumn([
                'category',
                'payment_method',
                'receipt_path',
                'receipt_original_name',
                'receipt_mime_type',
                'receipt_size_bytes',
            ]);
        });
    }
};
