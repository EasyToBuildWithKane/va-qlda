<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promote the placeholder `projects` table (tag picker) into a real
 * project-management entity. Existing columns (code/name/color/is_active/
 * sort_order) are kept so the daily-report picker keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('status')->default('planning')->after('color');
            $table->date('start_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('start_date');
            $table->decimal('budget', 15, 2)->nullable()->after('due_date');
            $table->foreignId('manager_id')->nullable()->after('budget')
                ->constrained('employees')->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['description', 'status', 'start_date', 'due_date', 'budget']);
        });
    }
};
