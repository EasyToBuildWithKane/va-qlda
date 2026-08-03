<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_report_scores')) {
            return;
        }

        Schema::table('daily_report_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_report_scores', 'scoring_snapshot')) {
                $table->json('scoring_snapshot')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_report_scores')) {
            return;
        }

        Schema::table('daily_report_scores', function (Blueprint $table) {
            if (Schema::hasColumn('daily_report_scores', 'scoring_snapshot')) {
                $table->dropColumn('scoring_snapshot');
            }
        });
    }
};
