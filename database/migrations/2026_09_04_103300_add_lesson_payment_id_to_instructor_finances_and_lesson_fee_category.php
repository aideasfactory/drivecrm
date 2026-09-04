<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->foreignId('lesson_payment_id')
                ->nullable()
                ->after('instructor_id')
                ->unique()
                ->constrained('lesson_payments')
                ->nullOnDelete();
        });

        $now = Carbon::now();

        DB::table('category_tax_mapping')->insertOrIgnore([
            'category' => 'lesson_fee',
            'vat_treatment' => 'exempt',
            'itsa_bucket' => null,
            'claimable' => false,
            'method_dependent' => false,
            'selectable_in_picker' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_payment_id');
        });

        DB::table('category_tax_mapping')->where('category', 'lesson_fee')->delete();
    }
};
