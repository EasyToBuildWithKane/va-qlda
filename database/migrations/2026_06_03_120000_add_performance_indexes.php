<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order', 'name'], 'projects_active_sort_name_idx');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->index(['employee_id', 'status'], 'daily_reports_employee_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_active_sort_name_idx');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropIndex('daily_reports_employee_status_idx');
        });
    }
};
