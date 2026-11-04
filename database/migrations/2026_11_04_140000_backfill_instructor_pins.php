<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every instructor without a PIN a unique 6-digit numeric PIN.
     * Existing PINs (numeric or not) are left unchanged.
     */
    public function up(): void
    {
        $usedPins = DB::table('instructors')
            ->whereNotNull('pin')
            ->where('pin', '!=', '')
            ->pluck('pin')
            ->flip()
            ->all();

        DB::table('instructors')
            ->where(fn ($query) => $query->whereNull('pin')->orWhere('pin', ''))
            ->orderBy('id')
            ->chunkById(200, function ($instructors) use (&$usedPins): void {
                foreach ($instructors as $instructor) {
                    do {
                        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    } while (isset($usedPins[$pin]));

                    $usedPins[$pin] = true;

                    DB::table('instructors')
                        ->where('id', $instructor->id)
                        ->update(['pin' => $pin]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Generated PINs are indistinguishable from pre-existing ones and may already
     * be shared with pupils, so they are intentionally not removed.
     */
    public function down(): void
    {
        //
    }
};
