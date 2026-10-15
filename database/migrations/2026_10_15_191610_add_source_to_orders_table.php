<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record where each order was booked from (App\Enums\OrderSource) so the
     * "Lessons booked" page can list every booking with its source. Existing
     * orders are backfilled where the source can be inferred; the rest (CRM,
     * learner app, instructor app) stay null and show as "Instructor / App".
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('booked_by_user_id');
            $table->index('created_at');
        });

        DB::table('orders')
            ->where('payment_mode', 'imported')
            ->update(['source' => 'imported']);

        DB::table('orders')
            ->whereNull('source')
            ->whereNotNull('booked_by_user_id')
            ->update(['source' => 'bookings_team']);

        // Website bookings are the remaining orders linked from an enquiry's step 6
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE orders
                INNER JOIN enquiries
                    ON CAST(JSON_UNQUOTE(JSON_EXTRACT(enquiries.data, '$.steps.step6.order_id')) AS UNSIGNED) = orders.id
                SET orders.source = 'website'
                WHERE orders.source IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('source');
        });
    }
};
