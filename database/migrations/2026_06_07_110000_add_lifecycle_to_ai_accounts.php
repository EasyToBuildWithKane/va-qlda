<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->string('lifecycle_status', 24)->default('in_use')->after('status');
            $table->foreignId('purchased_by')->nullable()->after('lifecycle_status')->constrained('system_accounts')->nullOnDelete();
            $table->unsignedBigInteger('actual_purchase_cost')->nullable()->after('purchased_by');
            $table->date('allocated_at')->nullable()->after('actual_purchase_cost');
            $table->string('allocated_to_name')->nullable()->after('allocated_at');

            $table->index('lifecycle_status', 'ai_acct_lifecycle_idx');
        });

        // Backfill lifecycle from existing status
        DB::table('ai_accounts')->get(['id', 'status', 'cost_amount', 'purchase_date'])->each(function ($account) {
            $lifecycle = match ($account->status) {
                'expired' => 'expired',
                'cancelled' => 'stopped',
                default => 'in_use',
            };

            DB::table('ai_accounts')->where('id', $account->id)->update([
                'lifecycle_status' => $lifecycle,
                'actual_purchase_cost' => $account->cost_amount,
                'allocated_at' => $account->purchase_date,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->dropIndex('ai_acct_lifecycle_idx');
            $table->dropForeign(['purchased_by']);
            $table->dropColumn([
                'lifecycle_status',
                'purchased_by',
                'actual_purchase_cost',
                'allocated_at',
                'allocated_to_name',
            ]);
        });
    }
};
