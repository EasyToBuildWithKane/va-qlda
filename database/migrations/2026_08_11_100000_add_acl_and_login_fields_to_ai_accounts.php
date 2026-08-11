<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('id')
                ->constrained('system_accounts')->nullOnDelete();
            $table->string('login_method', 16)->default('password')->after('email_registered');
            $table->string('purchase_url', 2048)->nullable()->after('notes');
        });

        Schema::create('ai_account_access_grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_account_id');
            $table->foreign('ai_account_id')->references('id')->on('ai_accounts')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->json('permissions');
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_account_id', 'account_id'], 'ai_acct_grant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_account_access_grants');

        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['login_method', 'purchase_url']);
        });
    }
};
