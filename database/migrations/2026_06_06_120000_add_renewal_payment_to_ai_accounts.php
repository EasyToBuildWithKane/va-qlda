<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->string('renewal_payment_status', 16)->default('unpaid')->after('status');
            $table->timestamp('renewal_paid_at')->nullable()->after('renewal_payment_status');
            $table->timestamp('last_payment_reminded_at')->nullable()->after('last_reminded_at');

            $table->index(['status', 'renewal_payment_status'], 'ai_acct_status_pay_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->dropIndex('ai_acct_status_pay_idx');
            $table->dropColumn([
                'renewal_payment_status',
                'renewal_paid_at',
                'last_payment_reminded_at',
            ]);
        });
    }
};
