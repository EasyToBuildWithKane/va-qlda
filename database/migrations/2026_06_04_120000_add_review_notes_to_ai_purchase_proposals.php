<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->dropColumn('review_notes');
        });
    }
};
