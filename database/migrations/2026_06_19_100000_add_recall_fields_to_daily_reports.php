<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            // When the owner pulled the report back to draft (last time), and how
            // many times in total — surfaced as a badge and in the audit timeline.
            $table->timestamp('recalled_at')->nullable()->after('reviewed_at');
            $table->unsignedInteger('recall_count')->default(0)->after('recalled_at');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['recalled_at', 'recall_count']);
        });
    }
};
