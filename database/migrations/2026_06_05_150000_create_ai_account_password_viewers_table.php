<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema cuối (scoped theo tài khoản AI). Migration 2026_06_06_100000
        // chỉ còn cần cho DB cũ từng tạo bản global-unique trước đó.
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
        Schema::dropIfExists('ai_account_password_viewers');
    }
};
