<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->string('tour_key', 64);
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->unsignedSmallInteger('total_steps')->default(0);
            $table->string('status', 16)->default('in_progress'); // in_progress | completed | skipped
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['system_account_id', 'tour_key'], 'onboarding_acct_tour_uq');
        });

        Schema::table('system_accounts', function (Blueprint $table) {
            // Drives the first-login Welcome screen; set once the user finishes or
            // dismisses the welcome flow (xem hoặc bỏ qua hướng dẫn).
            $table->timestamp('onboarding_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->dropColumn('onboarding_seen_at');
        });

        Schema::dropIfExists('onboarding_progress');
    }
};
