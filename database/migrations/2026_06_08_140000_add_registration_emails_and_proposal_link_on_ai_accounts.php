<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->json('registration_emails')->nullable()->after('registration_email');
        });

        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->foreignUuid('ai_purchase_proposal_id')
                ->nullable()
                ->after('id')
                ->constrained('ai_purchase_proposals')
                ->nullOnDelete();
        });

        if (Schema::hasTable('ai_purchase_proposals') && Schema::hasTable('ai_accounts')) {
            $links = DB::table('ai_purchase_proposals')
                ->whereNotNull('ai_account_id')
                ->get(['id', 'ai_account_id']);

            foreach ($links as $row) {
                DB::table('ai_accounts')
                    ->where('id', $row->ai_account_id)
                    ->whereNull('ai_purchase_proposal_id')
                    ->update(['ai_purchase_proposal_id' => $row->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_purchase_proposal_id');
        });

        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->dropColumn('registration_emails');
        });
    }
};
