<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_account_password_viewers', 'ai_account_id')) {
            return;
        }

        DB::table('ai_account_password_viewers')->delete();

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::drop('ai_account_password_viewers');
            Schema::create('ai_account_password_viewers', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('ai_account_id')->constrained('ai_accounts')->cascadeOnDelete();
                $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
                $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->timestamps();
                $table->unique(['ai_account_id', 'system_account_id'], 'ai_pwd_viewer_acct_user_uniq');
            });

            return;
        }

        Schema::table('ai_account_password_viewers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('system_account_id');
            $table->dropConstrainedForeignId('granted_by');
        });

        Schema::table('ai_account_password_viewers', function (Blueprint $table) {
            $table->dropUnique(['system_account_id']);
            $table->foreignUuid('ai_account_id')->after('id')->constrained('ai_accounts')->cascadeOnDelete();
            $table->unique(['ai_account_id', 'system_account_id'], 'ai_pwd_viewer_acct_user_uniq');
            $table->foreign('system_account_id')->references('id')->on('system_accounts')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('system_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('ai_account_password_viewers', 'ai_account_id')) {
            return;
        }

        DB::table('ai_account_password_viewers')->delete();

        Schema::table('ai_account_password_viewers', function (Blueprint $table) {
            $table->dropForeign(['granted_by']);
            $table->dropForeign(['system_account_id']);
            $table->dropUnique('ai_pwd_viewer_acct_user_uniq');
            $table->dropConstrainedForeignId('ai_account_id');
            $table->unique('system_account_id');
            $table->foreign('system_account_id')->references('id')->on('system_accounts')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('system_accounts')->nullOnDelete();
        });
    }
};
