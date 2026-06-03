<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chạy sau scope migration nếu production từng fail giữa chừng (thiếu cột hoặc thiếu unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_account_password_viewers')) {
            return;
        }

        if (Schema::hasColumn('ai_account_password_viewers', 'ai_account_id')
            && Schema::hasColumn('ai_account_password_viewers', 'system_account_id')) {
            return;
        }

        Schema::drop('ai_account_password_viewers');
        Schema::create('ai_account_password_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ai_account_id')->constrained('ai_accounts')->cascadeOnDelete();
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ai_account_id', 'system_account_id'], 'ai_pwd_viewer_acct_user_uniq');
        });
    }

    public function down(): void
    {
        // no-op — structure owned by scope migration
    }
};
