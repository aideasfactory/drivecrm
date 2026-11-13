<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users with a placeholder email (imported pupils who had no real address)
     * must never be sent an email. The mail guard drops any message to them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('exclude_from_emails')->default(false)->after('welcome_email_last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('exclude_from_emails');
        });
    }
};
