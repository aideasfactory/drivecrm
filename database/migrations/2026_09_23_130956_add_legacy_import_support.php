<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->after('welcome_email_pending')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_mode', ['upfront', 'weekly', 'imported'])->default('upfront')->change();
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 32);
            $table->string('source_ref', 191);
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->unique(['entity', 'source_ref']);
            $table->index(['entity', 'model_id']);
        });

        $now = Carbon::now();

        DB::table('category_tax_mapping')->updateOrInsert(
            ['category' => 'imported'],
            [
                'vat_treatment' => 'outside_scope',
                'itsa_bucket' => null,
                'claimable' => false,
                'method_dependent' => false,
                'selectable_in_picker' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('category_tax_mapping')->where('category', 'imported')->delete();

        Schema::dropIfExists('import_mappings');

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_mode', ['upfront', 'weekly'])->default('upfront')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['imported_at']);
            $table->dropColumn('imported_at');
        });
    }
};
