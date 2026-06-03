<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->foreignUuid('ai_account_id')
                ->nullable()
                ->after('status')
                ->constrained('ai_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_account_id');
        });
    }
};
