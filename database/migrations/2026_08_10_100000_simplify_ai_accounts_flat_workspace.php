<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Workspace 1A: một bảng ai_accounts phẳng + file PĐX/ĐNTT;
 * bỏ workflow PĐX/ĐNTT/OCR/password viewers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_proposal_scan_signatures');
        Schema::dropIfExists('ai_proposal_scans');
        Schema::dropIfExists('ai_payment_requests');
        Schema::dropIfExists('ai_account_password_viewers');

        $this->dropForeignIfNeeded('ai_accounts', 'ai_purchase_proposal_id');
        $this->dropForeignIfNeeded('ai_accounts', 'purchased_by');

        Schema::dropIfExists('ai_purchase_proposals');

        $dropColumns = array_values(array_filter([
            Schema::hasColumn('ai_accounts', 'ai_purchase_proposal_id') ? 'ai_purchase_proposal_id' : null,
            Schema::hasColumn('ai_accounts', 'license_type') ? 'license_type' : null,
            Schema::hasColumn('ai_accounts', 'license_key') ? 'license_key' : null,
            Schema::hasColumn('ai_accounts', 'seats') ? 'seats' : null,
            Schema::hasColumn('ai_accounts', 'lifecycle_status') ? 'lifecycle_status' : null,
            Schema::hasColumn('ai_accounts', 'purchased_by') ? 'purchased_by' : null,
            Schema::hasColumn('ai_accounts', 'actual_purchase_cost') ? 'actual_purchase_cost' : null,
            Schema::hasColumn('ai_accounts', 'allocated_at') ? 'allocated_at' : null,
            Schema::hasColumn('ai_accounts', 'allocated_to_name') ? 'allocated_to_name' : null,
            Schema::hasColumn('ai_accounts', 'renewal_payment_status') ? 'renewal_payment_status' : null,
            Schema::hasColumn('ai_accounts', 'renewal_paid_at') ? 'renewal_paid_at' : null,
            Schema::hasColumn('ai_accounts', 'last_payment_reminded_at') ? 'last_payment_reminded_at' : null,
            Schema::hasColumn('ai_accounts', 'status_locked_at') ? 'status_locked_at' : null,
        ]));

        if ($dropColumns !== []) {
            Schema::table('ai_accounts', function (Blueprint $table) {
                foreach (['ai_acct_lifecycle_idx', 'ai_acct_status_pay_idx'] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (\Throwable) {
                        // index may not exist on all drivers / fresh partial runs
                    }
                }
            });

            Schema::table('ai_accounts', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }

        Schema::table('ai_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_accounts', 'proposal_sent_at')) {
                $table->date('proposal_sent_at')->nullable();
            }
            if (! Schema::hasColumn('ai_accounts', 'payment_request_sent_at')) {
                $table->date('payment_request_sent_at')->nullable();
            }
            if (! Schema::hasColumn('ai_accounts', 'proposal_document_paths')) {
                $table->json('proposal_document_paths')->nullable();
            }
            if (! Schema::hasColumn('ai_accounts', 'payment_request_document_paths')) {
                $table->json('payment_request_document_paths')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            foreach ([
                'proposal_sent_at',
                'payment_request_sent_at',
                'proposal_document_paths',
                'payment_request_document_paths',
            ] as $col) {
                if (Schema::hasColumn('ai_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function dropForeignIfNeeded(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            try {
                $blueprint->dropConstrainedForeignId($column);
            } catch (\Throwable) {
                try {
                    $blueprint->dropForeign([$column]);
                } catch (\Throwable) {
                    // already gone
                }
            }
        });
    }
};
