<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->dropColumn('onboarding_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->timestamp('onboarding_seen_at')->nullable()->after('last_login_at');
        });
    }
};
