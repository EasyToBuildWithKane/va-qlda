<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->json('task_status_snapshot')->nullable()->after('plan_tomorrow');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('source', ['sprint', 'daily'])->default('sprint')->after('order_column');
            $table->foreignId('daily_report_id')
                ->nullable()
                ->after('source')
                ->constrained('daily_reports')
                ->nullOnDelete();
            $table->index('daily_report_id', 'tasks_daily_report_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_daily_report_id_idx');
            $table->dropForeign(['daily_report_id']);
            $table->dropColumn(['source', 'daily_report_id']);
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn('task_status_snapshot');
        });
    }
};
