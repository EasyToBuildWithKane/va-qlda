<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->date('proposal_approved_at')->nullable()->after('proposal_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            $table->dropColumn('proposal_approved_at');
        });
    }
};
