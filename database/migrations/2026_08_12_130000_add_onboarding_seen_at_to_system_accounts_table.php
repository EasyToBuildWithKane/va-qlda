<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-adds `onboarding_seen_at` on `system_accounts` for the full-screen
 * Welcome onboarding screen. The column previously existed (added by
 * 2026_06_17_140000_create_onboarding_progress_table) but was dropped by
 * 2026_07_28_180000_drop_onboarding_seen_at_from_system_accounts — this is a
 * fresh migration rather than editing either old one since they already ran
 * in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->timestamp('onboarding_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->dropColumn('onboarding_seen_at');
        });
    }
};
